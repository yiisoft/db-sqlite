<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Throwable;
use Yiisoft\Db\Constraint\CheckConstraint;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Constraint\ForeignKeyConstraint;
use Yiisoft\Db\Constraint\IndexConstraint;
use Yiisoft\Db\Driver\Pdo\AbstractPdoSchema;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Helper\DbArrayHelper;
use Yiisoft\Db\Schema\Builder\ColumnInterface;
use Yiisoft\Db\Schema\ColumnSchemaInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;

use function array_merge;
use function count;
use function explode;
use function md5;
use function preg_match;
use function serialize;
use function strncasecmp;
use function strtolower;
use function trim;

/**
 * Implements the SQLite Server specific schema, supporting SQLite 3.3.0 or higher.
 *
 * @psalm-type Column = array<array-key, array{seqno:string, cid:string, name:string}>
 * @psalm-type NormalizePragmaForeignKeyList = array<
 *   string,
 *   array<
 *     array-key,
 *     array{
 *       id:string,
 *       cid:string,
 *       seq:string,
 *       table:string,
 *       from:string,
 *       to:string,
 *       on_update:string,
 *       on_delete:string
 *     }
 *   >
 * >
 * @psalm-type PragmaForeignKeyList = array<
 *   string,
 *   array{
 *     id:string,
 *     cid:string,
 *     seq:string,
 *     table:string,
 *     from:string,
 *     to:string,
 *     on_update:string,
 *     on_delete:string
 *   }
 * >
 * @psalm-type PragmaIndexInfo = array<array-key, array{seqno:string, cid:string, name:string}>
 * @psalm-type PragmaIndexList = array<
 *   array-key,
 *   array{seq:string, name:string, unique:string, origin:string, partial:string}
 * >
 * @psalm-type PragmaTableInfo = array<
 *   array-key,
 *   array{cid:string, name:string, type:string, notnull:string, dflt_value:string|null, pk:string}
 * >
 */
final class Schema extends AbstractPdoSchema
{
    /**
     * @var array Mapping from physical column types (keys) to abstract column types (values).
     *
     * @psalm-var array<array-key, string> $typeMap
     */
    private array $typeMap = [
        'tinyint' => self::TYPE_TINYINT,
        'bit' => self::TYPE_SMALLINT,
        'boolean' => self::TYPE_BOOLEAN,
        'bool' => self::TYPE_BOOLEAN,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
        'numeric' => self::TYPE_DECIMAL,
        'tinytext' => self::TYPE_TEXT,
        'mediumtext' => self::TYPE_TEXT,
        'longtext' => self::TYPE_TEXT,
        'text' => self::TYPE_TEXT,
        'varchar' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        'char' => self::TYPE_CHAR,
        'blob' => self::TYPE_BINARY,
        'datetime' => self::TYPE_DATETIME,
        'year' => self::TYPE_DATE,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'enum' => self::TYPE_STRING,
    ];

    public function createColumn(string $type, array|int|string $length = null): ColumnInterface
    {
        return new Column($type, $length);
    }

    /**
     * Returns all table names in the database.
     *
     * This method should be overridden by child classes to support this feature because the default implementation
     * simply throws an exception.
     *
     * @param string $schema The schema of the tables.
     * Defaults to empty string, meaning the current or default schema.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array All tables name in the database. The names have NO schema name prefix.
     */
    protected function findTableNames(string $schema = ''): array
    {
        return $this->db
           ->createCommand(
               "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name"
           )
           ->queryColumn();
    }

    /**
     * Loads the metadata for the specified table.
     *
     * @param string $name The table name.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return TableSchemaInterface|null DBMS-dependent table metadata, `null` if the table doesn't exist.
     */
    protected function loadTableSchema(string $name): TableSchemaInterface|null
    {
        $table = new TableSchema();

        $table->name($name);
        $table->fullName($name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        }

        return null;
    }

    /**
     * Loads a primary key for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return Constraint|null Primary key for the given table, `null` if the table has no primary key.
     */
    protected function loadTablePrimaryKey(string $tableName): Constraint|null
    {
        $tablePrimaryKey = $this->loadTableConstraints($tableName, self::PRIMARY_KEY);

        return $tablePrimaryKey instanceof Constraint ? $tablePrimaryKey : null;
    }

    /**
     * Loads all foreign keys for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return ForeignKeyConstraint[] Foreign keys for the given table.
     */
    protected function loadTableForeignKeys(string $tableName): array
    {
        $result = [];
        /** @psalm-var PragmaForeignKeyList $foreignKeysList */
        $foreignKeysList = $this->getPragmaForeignKeyList($tableName);
        /** @psalm-var NormalizePragmaForeignKeyList $foreignKeysList */
        $foreignKeysList = $this->normalizeRowKeyCase($foreignKeysList, true);
        /** @psalm-var NormalizePragmaForeignKeyList $foreignKeysList */
        $foreignKeysList = DbArrayHelper::index($foreignKeysList, null, ['table']);
        DbArrayHelper::multisort($foreignKeysList, 'seq');

        /** @psalm-var NormalizePragmaForeignKeyList $foreignKeysList */
        foreach ($foreignKeysList as $table => $foreignKey) {
            $fk = (new ForeignKeyConstraint())
                ->columnNames(DbArrayHelper::getColumn($foreignKey, 'from'))
                ->foreignTableName($table)
                ->foreignColumnNames(DbArrayHelper::getColumn($foreignKey, 'to'))
                ->onDelete($foreignKey[0]['on_delete'] ?? null)
                ->onUpdate($foreignKey[0]['on_update'] ?? null);

            $result[] = $fk;
        }

        return $result;
    }

    /**
     * Loads all indexes for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array Indexes for the given table.
     *
     * @psalm-return array|IndexConstraint[]
     */
    protected function loadTableIndexes(string $tableName): array
    {
        $tableIndexes = $this->loadTableConstraints($tableName, self::INDEXES);

        return is_array($tableIndexes) ? $tableIndexes : [];
    }

    /**
     * Loads all unique constraints for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array Unique constraints for the given table.
     *
     * @psalm-return array|Constraint[]
     */
    protected function loadTableUniques(string $tableName): array
    {
        $tableUniques = $this->loadTableConstraints($tableName, self::UNIQUES);

        return is_array($tableUniques) ? $tableUniques : [];
    }

    /**
     * Loads all check constraints for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return CheckConstraint[] Check constraints for the given table.
     */
    protected function loadTableChecks(string $tableName): array
    {
        $sql = $this->db->createCommand(
            'SELECT `sql` FROM `sqlite_master` WHERE name = :tableName',
            [':tableName' => $tableName],
        )->queryScalar();

        $sql = ($sql === false || $sql === null) ? '' : (string) $sql;

        /** @psalm-var SqlToken[]|SqlToken[][]|SqlToken[][][] $code */
        $code = (new SqlTokenizer($sql))->tokenize();
        $pattern = (new SqlTokenizer('any CREATE any TABLE any()'))->tokenize();
        $result = [];

        if ($code[0] instanceof SqlToken && $code[0]->matches($pattern, 0, $firstMatchIndex, $lastMatchIndex)) {
            $offset = 0;
            $createTableToken = $code[0][(int) $lastMatchIndex - 1];
            $sqlTokenizerAnyCheck = new SqlTokenizer('any CHECK()');

            while (
                $createTableToken instanceof SqlToken &&
                $createTableToken->matches($sqlTokenizerAnyCheck->tokenize(), (int) $offset, $firstMatchIndex, $offset)
            ) {
                $name = null;
                $checkSql = (string) $createTableToken[(int) $offset - 1];
                $pattern = (new SqlTokenizer('CONSTRAINT any'))->tokenize();

                if (
                    isset($createTableToken[(int) $firstMatchIndex - 2])
                    && $createTableToken->matches($pattern, (int) $firstMatchIndex - 2)
                ) {
                    $sqlToken = $createTableToken[(int) $firstMatchIndex - 1];
                    $name = $sqlToken?->getContent();
                }

                $result[] = (new CheckConstraint())->name($name)->expression($checkSql);
            }
        }

        return $result;
    }

    /**
     * Loads all default value constraints for the given table.
     *
     * @param string $tableName The table name.
     *
     * @throws NotSupportedException
     *
     * @return array Default value constraints for the given table.
     */
    protected function loadTableDefaultValues(string $tableName): array
    {
        throw new NotSupportedException('SQLite does not support default value constraints.');
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchemaInterface $table The table metadata.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return bool Whether the table exists in the database.
     */
    protected function findColumns(TableSchemaInterface $table): bool
    {
        /** @psalm-var PragmaTableInfo $columns */
        $columns = $this->getPragmaTableInfo($table->getName());

        foreach ($columns as $info) {
            $column = $this->loadColumnSchema($info);
            $table->column($column->getName(), $column);

            if ($column->isPrimaryKey()) {
                $table->primaryKey($column->getName());
            }
        }

        $column = count($table->getPrimaryKey()) === 1 ? $table->getColumn($table->getPrimaryKey()[0]) : null;

        if ($column !== null && !strncasecmp($column->getDbType() ?? '', 'int', 3)) {
            $table->sequenceName('');
            $column->autoIncrement(true);
        }

        return !empty($columns);
    }

    /**
     * Collects the foreign key column details for the given table.
     *
     * @param TableSchemaInterface $table The table metadata.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected function findConstraints(TableSchemaInterface $table): void
    {
        /** @psalm-var PragmaForeignKeyList $foreignKeysList */
        $foreignKeysList = $this->getPragmaForeignKeyList($table->getName());

        foreach ($foreignKeysList as $foreignKey) {
            $id = (int) $foreignKey['id'];
            $fk = $table->getForeignKeys();

            if (!isset($fk[$id])) {
                $table->foreignKey($id, [$foreignKey['table'], $foreignKey['from'] => $foreignKey['to']]);
            } else {
                /** composite FK */
                $table->compositeForeignKey($id, $foreignKey['from'], $foreignKey['to']);
            }
        }
    }

    /**
     * Returns all unique indexes for the given table.
     *
     * Each array element is of the following structure:
     *
     * ```php
     * [
     *     'IndexName1' => ['col1' [, ...]],
     *     'IndexName2' => ['col2' [, ...]],
     * ]
     * ```
     *
     * @param TableSchemaInterface $table The table metadata.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array All unique indexes for the given table.
     */
    public function findUniqueIndexes(TableSchemaInterface $table): array
    {
        /** @psalm-var PragmaIndexList $indexList */
        $indexList = $this->getPragmaIndexList($table->getName());
        $uniqueIndexes = [];

        foreach ($indexList as $index) {
            $indexName = $index['name'];
            /** @psalm-var PragmaIndexInfo $indexInfo */
            $indexInfo = $this->getPragmaIndexInfo($index['name']);

            if ($index['unique']) {
                $uniqueIndexes[$indexName] = [];
                foreach ($indexInfo as $row) {
                    $uniqueIndexes[$indexName][] = $row['name'];
                }
            }
        }

        return $uniqueIndexes;
    }

    /**
     * @throws NotSupportedException
     */
    public function getSchemaDefaultValues(string $schema = '', bool $refresh = false): array
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Loads the column information into a {@see ColumnSchemaInterface} object.
     *
     * @param array $info The column information.
     *
     * @return ColumnSchemaInterface The column schema object.
     *
     * @psalm-param array{cid:string, name:string, type:string, notnull:string, dflt_value:string|null, pk:string} $info
     */
    protected function loadColumnSchema(array $info): ColumnSchemaInterface
    {
        $column = $this->createColumnSchema($info['name']);
        $column->allowNull(!$info['notnull']);
        $column->primaryKey($info['pk'] != '0');
        $column->dbType(strtolower($info['type']));
        $column->unsigned(str_contains($column->getDbType() ?? '', 'unsigned'));
        $column->type(self::TYPE_STRING);

        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/', $column->getDbType() ?? '', $matches)) {
            $type = strtolower($matches[1]);

            if (isset($this->typeMap[$type])) {
                $column->type($this->typeMap[$type]);
            }

            if (!empty($matches[2])) {
                $values = explode(',', $matches[2]);
                $column->precision((int) $values[0]);
                $column->size((int) $values[0]);

                if (isset($values[1])) {
                    $column->scale((int) $values[1]);
                }

                if (($type === 'tinyint' || $type === 'bit') && $column->getSize() === 1) {
                    $column->type(self::TYPE_BOOLEAN);
                } elseif ($type === 'bit') {
                    if ($column->getSize() > 32) {
                        $column->type(self::TYPE_BIGINT);
                    } elseif ($column->getSize() === 32) {
                        $column->type(self::TYPE_INTEGER);
                    }
                }
            }
        }

        $column->phpType($this->getColumnPhpType($column));
        $column->defaultValue($this->normalizeDefaultValue($info['dflt_value'], $column));

        return $column;
    }

    /**
     * Converts column's default value according to {@see ColumnSchema::phpType} after retrieval from the database.
     *
     * @param string|null $defaultValue The default value retrieved from the database.
     * @param ColumnSchemaInterface $columnSchema The column schema object.
     *
     * @return mixed The normalized default value.
     *
     * @psalm-suppress PossiblyNullArgument
     */
    private function normalizeDefaultValue(?string $defaultValue, ColumnSchemaInterface $columnSchema): mixed
    {
        return match (true) {
            $defaultValue === null,
            $defaultValue === 'null',
            $defaultValue === '',
            $columnSchema->isPrimaryKey()
                => null,
            in_array($defaultValue, [
                'CURRENT_TIMESTAMP',
                'CURRENT_DATE',
                'CURRENT_TIME',
            ], true)
                => new Expression($defaultValue),
            default
            /** @psalm-var string $defaultValue */
            => $columnSchema->phpTypecast(trim($defaultValue, "'\"")),
        };
    }

    /**
     * Returns table columns info.
     *
     * @param string $tableName The table name.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @return array The table columns info.
     */
    private function loadTableColumnsInfo(string $tableName): array
    {
        $tableColumns = $this->getPragmaTableInfo($tableName);
        /** @psalm-var PragmaTableInfo $tableColumns */
        $tableColumns = $this->normalizeRowKeyCase($tableColumns, true);

        return DbArrayHelper::index($tableColumns, 'cid');
    }

    /**
     * Loads multiple types of constraints and returns the specified ones.
     *
     * @param string $tableName The table name.
     * @param string $returnType Return type: (primaryKey, indexes, uniques).
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-return Constraint[]|IndexConstraint[]|Constraint|null
     */
    private function loadTableConstraints(string $tableName, string $returnType): Constraint|array|null
    {
        $indexList = $this->getPragmaIndexList($tableName);
        /** @psalm-var PragmaIndexList $indexes */
        $indexes = $this->normalizeRowKeyCase($indexList, true);
        $result = [
            self::PRIMARY_KEY => null,
            self::INDEXES => [],
            self::UNIQUES => [],
        ];

        foreach ($indexes as $index) {
            /** @psalm-var Column $columns */
            $columns = $this->getPragmaIndexInfo($index['name']);

            if ($index['origin'] === 'pk') {
                $result[self::PRIMARY_KEY] = (new Constraint())
                    ->columnNames(DbArrayHelper::getColumn($columns, 'name'));
            }

            if ($index['origin'] === 'u') {
                $result[self::UNIQUES][] = (new Constraint())
                    ->name($index['name'])
                    ->columnNames(DbArrayHelper::getColumn($columns, 'name'));
            }

            $result[self::INDEXES][] = (new IndexConstraint())
                ->primary($index['origin'] === 'pk')
                ->unique((bool) $index['unique'])
                ->name($index['name'])
                ->columnNames(DbArrayHelper::getColumn($columns, 'name'));
        }

        if (!isset($result[self::PRIMARY_KEY])) {
            /**
             * Extra check for PK in case of `INTEGER PRIMARY KEY` with ROWID.
             *
             * @link https://www.sqlite.org/lang_createtable.html#primkeyconst
             *
             * @psalm-var PragmaTableInfo $tableColumns
             */
            $tableColumns = $this->loadTableColumnsInfo($tableName);

            foreach ($tableColumns as $tableColumn) {
                if ($tableColumn['pk'] > 0) {
                    $result[self::PRIMARY_KEY] = (new Constraint())->columnNames([$tableColumn['name']]);
                    break;
                }
            }
        }

        foreach ($result as $type => $data) {
            $this->setTableMetadata($tableName, $type, $data);
        }

        return $result[$returnType];
    }

    /**
     * Creates a column schema for the database.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema.
     *
     * @param string $name Name of the column.
     */
    private function createColumnSchema(string $name): ColumnSchemaInterface
    {
        return new ColumnSchema($name);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    private function getPragmaForeignKeyList(string $tableName): array
    {
        return $this->db->createCommand(
            'PRAGMA FOREIGN_KEY_LIST(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')'
        )->queryAll();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    private function getPragmaIndexInfo(string $name): array
    {
        $column = $this->db
            ->createCommand('PRAGMA INDEX_INFO(' . (string) $this->db->getQuoter()->quoteValue($name) . ')')
            ->queryAll();
        /** @psalm-var Column $column */
        $column = $this->normalizeRowKeyCase($column, true);
        DbArrayHelper::multisort($column, 'seqno');

        return $column;
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    private function getPragmaIndexList(string $tableName): array
    {
        return $this->db
            ->createCommand('PRAGMA INDEX_LIST(' . (string) $this->db->getQuoter()->quoteValue($tableName) . ')')
            ->queryAll();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    private function getPragmaTableInfo(string $tableName): array
    {
        return $this->db->createCommand(
            'PRAGMA TABLE_INFO(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')'
        )->queryAll();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected function findViewNames(string $schema = ''): array
    {
        /** @psalm-var string[][] $views */
        $views = $this->db->createCommand(
            <<<SQL
            SELECT name as view FROM sqlite_master WHERE type = 'view' AND name NOT LIKE 'sqlite_%'
            SQL,
        )->queryAll();

        foreach ($views as $key => $view) {
            $views[$key] = $view['view'];
        }

        return $views;
    }

    /**
     * Returns the cache key for the specified table name.
     *
     * @param string $name the table name.
     *
     * @return array The cache key.
     */
    protected function getCacheKey(string $name): array
    {
        return array_merge([self::class], $this->generateCacheKey(), [$this->getRawTableName($name)]);
    }

    /**
     * Returns the cache tag name.
     *
     * This allows {@see refresh()} to invalidate all cached table schemas.
     *
     * @return string The cache tag name.
     */
    protected function getCacheTag(): string
    {
        return md5(serialize(array_merge([self::class], $this->generateCacheKey())));
    }
}
