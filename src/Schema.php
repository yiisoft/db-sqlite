<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Throwable;
use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Constraint\CheckConstraint;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Constraint\ForeignKeyConstraint;
use Yiisoft\Db\Constraint\IndexConstraint;
use Yiisoft\Db\Driver\Pdo\AbstractPdoSchema;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Helper\DbArrayHelper;
use Yiisoft\Db\Schema\Column\ColumnFactoryInterface;
use Yiisoft\Db\Schema\Column\ColumnInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;
use Yiisoft\Db\Sqlite\Column\ColumnFactory;

use function array_change_key_case;
use function array_column;
use function array_map;
use function count;
use function md5;
use function serialize;
use function strncasecmp;

/**
 * Implements the SQLite Server specific schema, supporting SQLite 3.3.0 or higher.
 *
 * @psalm-type ForeignKeyInfo = array{
 *     id:string,
 *     cid:string,
 *     seq:string,
 *     table:string,
 *     from:string,
 *     to:string|null,
 *     on_update:string,
 *     on_delete:string
 * }
 * @psalm-type GroupedForeignKeyInfo = array<
 *     string,
 *     ForeignKeyInfo[]
 * >
 * @psalm-type IndexInfo = array{
 *     seqno:string,
 *     cid:string,
 *     name:string
 * }
 * @psalm-type IndexListInfo = array{
 *     seq:string,
 *     name:string,
 *     unique:string,
 *     origin:string,
 *     partial:string
 * }
 * @psalm-type ColumnInfo = array{
 *     cid:string,
 *     name:string,
 *     type:string,
 *     notnull:string,
 *     dflt_value:string|null,
 *     pk:string,
 *     size?: int,
 *     scale?: int,
 *     schema: string|null,
 *     table: string
 * }
 */
final class Schema extends AbstractPdoSchema
{
    public function getColumnFactory(): ColumnFactoryInterface
    {
        return new ColumnFactory();
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

        $foreignKeysList = $this->getPragmaForeignKeyList($tableName);
        /** @psalm-var GroupedForeignKeyInfo $foreignKeysList */
        $foreignKeysList = DbArrayHelper::index($foreignKeysList, null, ['table', 'id']);

        foreach ($foreignKeysList as $table => $foreignKeysById) {
            /**
             * @psalm-var GroupedForeignKeyInfo $foreignKeysById
             * @psalm-var int $id
             */
            foreach ($foreignKeysById as $id => $foreignKey) {
                if ($foreignKey[0]['to'] === null) {
                    $primaryKey = $this->getTablePrimaryKey($table);

                    if ($primaryKey !== null) {
                        foreach ((array) $primaryKey->getColumnNames() as $i => $primaryKeyColumnName) {
                            $foreignKey[$i]['to'] = $primaryKeyColumnName;
                        }
                    }
                }

                $fk = (new ForeignKeyConstraint())
                    ->name((string) $id)
                    ->columnNames(array_column($foreignKey, 'from'))
                    ->foreignTableName($table)
                    ->foreignColumnNames(array_column($foreignKey, 'to'))
                    ->onDelete($foreignKey[0]['on_delete'])
                    ->onUpdate($foreignKey[0]['on_update']);

                $result[] = $fk;
            }
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
     * @psalm-return IndexConstraint[]
     */
    protected function loadTableIndexes(string $tableName): array
    {
        /** @var IndexConstraint[] */
        return $this->loadTableConstraints($tableName, self::INDEXES);
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
        $columns = $this->getPragmaTableInfo($table->getName());
        $jsonColumns = $this->getJsonColumns($table);

        foreach ($columns as $info) {
            if (in_array($info['name'], $jsonColumns, true)) {
                $info['type'] = ColumnType::JSON;
            }

            $info['schema'] = $table->getSchemaName();
            $info['table'] = $table->getName();

            $column = $this->loadColumn($info);
            $table->column($info['name'], $column);

            if ($column->isPrimaryKey()) {
                $table->primaryKey($info['name']);
            }
        }

        $column = count($table->getPrimaryKey()) === 1 ? $table->getColumn($table->getPrimaryKey()[0]) : null;

        if ($column !== null && !strncasecmp($column->getDbType() ?? '', 'int', 3)) {
            $table->sequenceName('');
            $column->autoIncrement();
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
        /** @psalm-var ForeignKeyConstraint[] $foreignKeysList */
        $foreignKeysList = $this->getTableForeignKeys($table->getName(), true);

        foreach ($foreignKeysList as $foreignKey) {
            /** @var array<string> $columnNames */
            $columnNames = (array) $foreignKey->getColumnNames();
            $columnNames = array_combine($columnNames, $foreignKey->getForeignColumnNames());

            $foreignReference = [$foreignKey->getForeignTableName(), ...$columnNames];

            /** @psalm-suppress InvalidCast */
            $table->foreignKey((string) $foreignKey->getName(), $foreignReference);
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
        /** @psalm-var IndexListInfo[] $indexList */
        $indexList = $this->getPragmaIndexList($table->getName());
        $uniqueIndexes = [];

        foreach ($indexList as $index) {
            $indexName = $index['name'];
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
     * Loads the column information into a {@see ColumnInterface} object.
     *
     * @param array $info The column information.
     *
     * @return ColumnInterface The column object.
     *
     * @psalm-param ColumnInfo $info
     */
    private function loadColumn(array $info): ColumnInterface
    {
        return $this->getColumnFactory()->fromDefinition($info['type'], [
            'defaultValueRaw' => $info['dflt_value'],
            'name' => $info['name'],
            'notNull' => (bool) $info['notnull'],
            'primaryKey' => (bool) $info['pk'],
            'schema' => $info['schema'],
            'table' => $info['table'],
        ]);
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
     *
     * @psalm-return ColumnInfo[] $tableColumns;
     */
    private function loadTableColumnsInfo(string $tableName): array
    {
        $tableColumns = $this->getPragmaTableInfo($tableName);
        /** @psalm-var ColumnInfo[] $tableColumns */
        $tableColumns = array_map(array_change_key_case(...), $tableColumns);

        /** @psalm-var ColumnInfo[] */
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
        /** @psalm-var IndexListInfo[] $indexes */
        $indexes = array_map(array_change_key_case(...), $indexList);
        $result = [
            self::PRIMARY_KEY => null,
            self::INDEXES => [],
            self::UNIQUES => [],
        ];

        foreach ($indexes as $index) {
            $columns = $this->getPragmaIndexInfo($index['name']);

            if ($index['origin'] === 'pk') {
                $result[self::PRIMARY_KEY] = (new Constraint())
                    ->columnNames(array_column($columns, 'name'));
            }

            if ($index['origin'] === 'u') {
                $result[self::UNIQUES][] = (new Constraint())
                    ->name($index['name'])
                    ->columnNames(array_column($columns, 'name'));
            }

            $result[self::INDEXES][] = (new IndexConstraint())
                ->primary($index['origin'] === 'pk')
                ->unique((bool) $index['unique'])
                ->name($index['name'])
                ->columnNames(array_column($columns, 'name'));
        }

        if (!isset($result[self::PRIMARY_KEY])) {
            /**
             * Extra check for PK in case of `INTEGER PRIMARY KEY` with ROWID.
             *
             * @link https://www.sqlite.org/lang_createtable.html#primkeyconst
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
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-return ForeignKeyInfo[]
     */
    private function getPragmaForeignKeyList(string $tableName): array
    {
        $foreignKeysList = $this->db->createCommand(
            'PRAGMA FOREIGN_KEY_LIST(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')'
        )->queryAll();
        $foreignKeysList = array_map(array_change_key_case(...), $foreignKeysList);
        DbArrayHelper::multisort($foreignKeysList, 'seq');

        /** @psalm-var ForeignKeyInfo[] $foreignKeysList */
        return $foreignKeysList;
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-return IndexInfo[]
     */
    private function getPragmaIndexInfo(string $name): array
    {
        $column = $this->db
            ->createCommand('PRAGMA INDEX_INFO(' . $this->db->getQuoter()->quoteValue($name) . ')')
            ->queryAll();
        $column = array_map(array_change_key_case(...), $column);
        DbArrayHelper::multisort($column, 'seqno');

        /** @psalm-var IndexInfo[] $column */
        return $column;
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-return IndexListInfo[]
     */
    private function getPragmaIndexList(string $tableName): array
    {
        /** @psalm-var IndexListInfo[] */
        return $this->db
            ->createCommand('PRAGMA INDEX_LIST(' . $this->db->getQuoter()->quoteValue($tableName) . ')')
            ->queryAll();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     *
     * @psalm-return ColumnInfo[]
     */
    private function getPragmaTableInfo(string $tableName): array
    {
        /** @psalm-var ColumnInfo[] */
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
        /** @var string[][] $views */
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
        return [self::class, ...$this->generateCacheKey(), $this->db->getQuoter()->getRawTableName($name)];
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
        return md5(serialize([self::class, ...$this->generateCacheKey()]));
    }

    /**
     * @throws Throwable
     */
    private function getJsonColumns(TableSchemaInterface $table): array
    {
        $result = [];
        /** @psalm-var CheckConstraint[] $checks */
        $checks = $this->getTableChecks((string) $table->getFullName());
        $regexp = '/\bjson_valid\(\s*["`\[]?(.+?)["`\]]?\s*\)/i';

        foreach ($checks as $check) {
            if (preg_match_all($regexp, $check->getExpression(), $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    $result[] = $match[1];
                }
            }
        }

        return $result;
    }
}
