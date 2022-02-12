<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\PDO;

use PDO;
use Throwable;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Arrays\ArraySorter;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionPDOInterface;
use Yiisoft\Db\Constraint\CheckConstraint;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Constraint\ForeignKeyConstraint;
use Yiisoft\Db\Constraint\IndexConstraint;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Schema\ColumnSchema;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Sqlite\ColumnSchemaBuilder;
use Yiisoft\Db\Sqlite\SqlToken;
use Yiisoft\Db\Sqlite\SqlTokenizer;
use Yiisoft\Db\Sqlite\TableSchema;

use function count;
use function explode;
use function preg_match;
use function strncasecmp;
use function strpos;
use function strtolower;
use function trim;

/**
 * Schema is the class for retrieving metadata from a SQLite (2/3) database.
 *
 * @property string $transactionIsolationLevel The transaction isolation level to use for this transaction. This can be
 * either {@see TransactionPDOSqlite::READ_UNCOMMITTED} or {@see TransactionPDOSqlite::SERIALIZABLE}.
 *
 * @psalm-type Column = array<array-key, array{seqno:string, cid:string, name:string}>
 *
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
 *
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
 *
 * @psalm-type PragmaIndexInfo = array<array-key, array{seqno:string, cid:string, name:string}>
 *
 * @psalm-type PragmaIndexList = array<
 *   array-key,
 *   array{seq:string, name:string, unique:string, origin:string, partial:string}
 * >
 *
 * @psalm-type PragmaTableInfo = array<
 *   array-key,
 *   array{cid:string, name:string, type:string, notnull:string, dflt_value:string|null, pk:string}
 * >
 */
final class SchemaPDOSqlite extends Schema
{
    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
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

    public function __construct(private ConnectionPDOInterface $db, SchemaCache $schemaCache)
    {
        parent::__construct($schemaCache);
    }

    /**
     * Returns all table names in the database.
     *
     * This method should be overridden by child classes in order to support this feature because the default
     * implementation simply throws an exception.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return array all table names in the database. The names have NO schema name prefix.
     */
    protected function findTableNames(string $schema = ''): array
    {
        return $this->db->createCommand(
            "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name"
        )->queryColumn();
    }

    public function insert(string $table, array $columns): bool|array
    {
        $command = $this->db->createCommand()->insert($table, $columns);
        $tablePrimaryKey = [];

        if (!$command->execute()) {
            return false;
        }

        $tableSchema = $this->getTableSchema($table);
        $result = [];

        if ($tableSchema !== null) {
            $tablePrimaryKey = $tableSchema->getPrimaryKey();
        }

        /** @var string $name */
        foreach ($tablePrimaryKey as $name) {
            if ($tableSchema?->getColumn($name)?->isAutoIncrement()) {
                $result[$name] = $this->getLastInsertID((string) $tableSchema?->getSequenceName());
                break;
            }

            /** @var mixed */
            $result[$name] = $columns[$name] ?? $tableSchema?->getColumn($name)?->getDefaultValue();
        }

        return $result;
    }

    /**
     * Loads the metadata for the specified table.
     *
     * @param string $name table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return TableSchema|null DBMS-dependent table metadata, `null` if the table does not exist.
     */
    protected function loadTableSchema(string $name): ?TableSchema
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
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return Constraint|null primary key for the given table, `null` if the table has no primary key.
     */
    protected function loadTablePrimaryKey(string $tableName): ?Constraint
    {
        $tablePrimaryKey = $this->loadTableConstraints($tableName, 'primaryKey');

        return $tablePrimaryKey instanceof Constraint ? $tablePrimaryKey : null;
    }

    /**
     * Loads all foreign keys for the given table.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return ForeignKeyConstraint[] foreign keys for the given table.
     */
    protected function loadTableForeignKeys(string $tableName): array
    {
        $result = [];
        /** @psalm-var PragmaForeignKeyList */
        $foreignKeysList = $this->getPragmaForeignKeyList($tableName);
        /** @psalm-var NormalizePragmaForeignKeyList */
        $foreignKeysList = $this->normalizePdoRowKeyCase($foreignKeysList, true);
        /** @psalm-var NormalizePragmaForeignKeyList */
        $foreignKeysList = ArrayHelper::index($foreignKeysList, null, 'table');
        ArraySorter::multisort($foreignKeysList, 'seq', SORT_ASC, SORT_NUMERIC);

        /** @psalm-var NormalizePragmaForeignKeyList $foreignKeysList */
        foreach ($foreignKeysList as $table => $foreignKey) {
            $fk = (new ForeignKeyConstraint())
                ->columnNames(ArrayHelper::getColumn($foreignKey, 'from'))
                ->foreignTableName($table)
                ->foreignColumnNames(ArrayHelper::getColumn($foreignKey, 'to'))
                ->onDelete($foreignKey[0]['on_delete'] ?? null)
                ->onUpdate($foreignKey[0]['on_update'] ?? null);

            $result[] = $fk;
        }

        return $result;
    }

    /**
     * Loads all indexes for the given table.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return array|IndexConstraint[] indexes for the given table.
     */
    protected function loadTableIndexes(string $tableName): array
    {
        $tableIndexes = $this->loadTableConstraints($tableName, 'indexes');

        return is_array($tableIndexes) ? $tableIndexes : [];
    }

    /**
     * Loads all unique constraints for the given table.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return array|Constraint[] unique constraints for the given table.
     */
    protected function loadTableUniques(string $tableName): array
    {
        $tableUniques = $this->loadTableConstraints($tableName, 'uniques');

        return is_array($tableUniques) ? $tableUniques : [];
    }

    /**
     * Loads all check constraints for the given table.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return CheckConstraint[] check constraints for the given table.
     */
    protected function loadTableChecks(string $tableName): array
    {
        $sql = $this->db->createCommand(
            'SELECT `sql` FROM `sqlite_master` WHERE name = :tableName',
            [':tableName' => $tableName],
        )->queryScalar();

        $sql = ($sql === false || $sql === null) ? '' : (string) $sql;

        /** @var SqlToken[]|SqlToken[][]|SqlToken[][][] $code */
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
     * @param string $tableName table name.
     *
     * @throws NotSupportedException
     *
     * @return array default value constraints for the given table.
     */
    protected function loadTableDefaultValues(string $tableName): array
    {
        throw new NotSupportedException('SQLite does not support default value constraints.');
    }

    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema builder.
     *
     * @param string $type type of the column. See {@see ColumnSchemaBuilder::$type}.
     * @param array|int|string|null $length length or precision of the column. See {@see ColumnSchemaBuilder::$length}.
     *
     * @return ColumnSchemaBuilder column schema builder instance.
     */
    public function createColumnSchemaBuilder(string $type, $length = null): ColumnSchemaBuilder
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchema $table the table metadata.
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return bool whether the table exists in the database.
     */
    protected function findColumns(TableSchema $table): bool
    {
        /** @psalm-var PragmaTableInfo */
        $columns = $this->getPragmaTableInfo($table->getName());

        foreach ($columns as $info) {
            $column = $this->loadColumnSchema($info);
            $table->columns($column->getName(), $column);

            if ($column->isPrimaryKey()) {
                $table->primaryKey($column->getName());
            }
        }

        $column = count($table->getPrimaryKey()) === 1 ? $table->getColumn((string) $table->getPrimaryKey()[0]) : null;

        if ($column !== null && !strncasecmp($column->getDbType(), 'int', 3)) {
            $table->sequenceName('');
            $column->autoIncrement(true);
        }

        return !empty($columns);
    }

    /**
     * Collects the foreign key column details for the given table.
     *
     * @param TableSchema $table the table metadata.
     *
     * @throws Exception|InvalidConfigException|Throwable
     */
    protected function findConstraints(TableSchema $table): void
    {
        /** @psalm-var PragmaForeignKeyList */
        $foreignKeysList = $this->getPragmaForeignKeyList($table->getName());

        foreach ($foreignKeysList as $foreignKey) {
            $id = (int) $foreignKey['id'];
            $fk = $table->getForeignKeys();

            if (!isset($fk[$id])) {
                $table->foreignKey($id, ([$foreignKey['table'], $foreignKey['from'] => $foreignKey['to']]));
            } else {
                /** composite FK */
                $table->compositeFK($id, $foreignKey['from'], $foreignKey['to']);
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
     * @param TableSchema $table the table metadata.
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return array all unique indexes for the given table.
     */
    public function findUniqueIndexes(TableSchema $table): array
    {
        /** @psalm-var PragmaIndexList */
        $indexList = $this->getPragmaIndexList($table->getName());
        $uniqueIndexes = [];

        foreach ($indexList as $index) {
            $indexName = $index['name'];
            /** @psalm-var PragmaIndexInfo */
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
     * Loads the column information into a {@see ColumnSchema} object.
     *
     * @param array $info column information.
     *
     * @return ColumnSchema the column schema object.
     *
     * @psalm-param array{cid:string, name:string, type:string, notnull:string, dflt_value:string|null, pk:string} $info
     */
    protected function loadColumnSchema(array $info): ColumnSchema
    {
        $column = $this->createColumnSchema();
        $column->name($info['name']);
        $column->allowNull(!$info['notnull']);
        $column->primaryKey($info['pk'] !== '0');
        $column->dbType(strtolower($info['type']));
        $column->unsigned(str_contains($column->getDbType(), 'unsigned'));
        $column->type(self::TYPE_STRING);

        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/', $column->getDbType(), $matches)) {
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

                if ($column->getSize() === 1 && ($type === 'tinyint' || $type === 'bit')) {
                    $column->type('boolean');
                } elseif ($type === 'bit') {
                    if ($column->getSize() > 32) {
                        $column->type('bigint');
                    } elseif ($column->getSize() === 32) {
                        $column->type('integer');
                    }
                }
            }
        }

        $column->phpType($this->getColumnPhpType($column));

        if (!$column->isPrimaryKey()) {
            if ($info['dflt_value'] === 'null' || $info['dflt_value'] === '' || $info['dflt_value'] === null) {
                $column->defaultValue(null);
            } elseif ($column->getType() === 'timestamp' && $info['dflt_value'] === 'CURRENT_TIMESTAMP') {
                $column->defaultValue(new Expression('CURRENT_TIMESTAMP'));
            } else {
                $value = trim($info['dflt_value'], "'\"");
                $column->defaultValue($column->phpTypecast($value));
            }
        }

        return $column;
    }

    /**
     * Sets the isolation level of the current transaction.
     *
     * @param string $level The transaction isolation level to use for this transaction. This can be either
     * {@see TransactionPDOSqlite::READ_UNCOMMITTED} or {@see TransactionPDOSqlite::SERIALIZABLE}.
     *
     * @throws Exception|InvalidConfigException|NotSupportedException|Throwable when unsupported isolation levels are
     * used. SQLite only supports SERIALIZABLE and READ UNCOMMITTED.
     *
     * {@see http://www.sqlite.org/pragma.html#pragma_read_uncommitted}
     */
    public function setTransactionIsolationLevel(string $level): void
    {
        switch ($level) {
            case TransactionPDOSqlite::SERIALIZABLE:
                $this->db->createCommand('PRAGMA read_uncommitted = False;')->execute();
                break;
            case TransactionPDOSqlite::READ_UNCOMMITTED:
                $this->db->createCommand('PRAGMA read_uncommitted = True;')->execute();
                break;
            default:
                throw new NotSupportedException(
                    self::class . ' only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
                );
        }
    }

    /**
     * Returns table columns info.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return array
     */
    private function loadTableColumnsInfo(string $tableName): array
    {
        $tableColumns = $this->getPragmaTableInfo($tableName);
        /** @psalm-var PragmaTableInfo */
        $tableColumns = $this->normalizePdoRowKeyCase($tableColumns, true);

        return ArrayHelper::index($tableColumns, 'cid');
    }

    /**
     * Loads multiple types of constraints and returns the specified ones.
     *
     * @param string $tableName table name.
     * @param string $returnType return type: (primaryKey, indexes, uniques).
     *
     * @throws Exception|InvalidConfigException|Throwable
     *
     * @return (Constraint|IndexConstraint)[]|Constraint|null
     */
    private function loadTableConstraints(string $tableName, string $returnType)
    {
        $indexList = $this->getPragmaIndexList($tableName);
        /** @psalm-var PragmaIndexList $indexes */
        $indexes = $this->normalizePdoRowKeyCase($indexList, true);
        $result = ['primaryKey' => null, 'indexes' => [], 'uniques' => []];

        foreach ($indexes as $index) {
            /** @psalm-var Column $columns */
            $columns = $this->getPragmaIndexInfo($index['name']);

            $result['indexes'][] = (new IndexConstraint())
                ->primary($index['origin'] === 'pk')
                ->unique((bool) $index['unique'])
                ->name($index['name'])
                ->columnNames(ArrayHelper::getColumn($columns, 'name'));

            if ($index['origin'] === 'u') {
                $result['uniques'][] = (new Constraint())
                    ->name($index['name'])
                    ->columnNames(ArrayHelper::getColumn($columns, 'name'));
            }

            if ($index['origin'] === 'pk') {
                $result['primaryKey'] = (new Constraint())
                    ->columnNames(ArrayHelper::getColumn($columns, 'name'));
            }
        }

        if (!isset($result['primaryKey'])) {
            /**
             * Additional check for PK in case of INTEGER PRIMARY KEY with ROWID.
             *
             * {@See https://www.sqlite.org/lang_createtable.html#primkeyconst}
             *
             * @psalm-var PragmaTableInfo
             */
            $tableColumns = $this->loadTableColumnsInfo($tableName);

            foreach ($tableColumns as $tableColumn) {
                if ($tableColumn['pk'] > 0) {
                    $result['primaryKey'] = (new Constraint())->columnNames([$tableColumn['name']]);
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
     * @return ColumnSchema column schema instance.
     */
    private function createColumnSchema(): ColumnSchema
    {
        return new ColumnSchema();
    }

    /**
     * @throws Exception|InvalidConfigException|Throwable
     */
    private function getPragmaForeignKeyList(string $tableName): array
    {
        return $this->db->createCommand(
            'PRAGMA FOREIGN_KEY_LIST(' . $this->db->getQuoter()->quoteSimpleTableName(($tableName)) . ')'
        )->queryAll();
    }

    /**
     * @throws Exception|InvalidConfigException|Throwable
     */
    private function getPragmaIndexInfo(string $name): array
    {
        $column = $this->db
            ->createCommand('PRAGMA INDEX_INFO(' . $this->db->getQuoter()->quoteValue($name) . ')')->queryAll();
        /** @psalm-var Column */
        $column = $this->normalizePdoRowKeyCase($column, true);
        ArraySorter::multisort($column, 'seqno', SORT_ASC, SORT_NUMERIC);

        return $column;
    }

    /**
     * @throws Exception|InvalidConfigException|Throwable
     */
    private function getPragmaIndexList(string $tableName): array
    {
        return $this->db
            ->createCommand('PRAGMA INDEX_LIST(' . $this->db->getQuoter()->quoteValue($tableName) . ')')->queryAll();
    }

    /**
     * @throws Exception|InvalidConfigException|Throwable
     */
    private function getPragmaTableInfo(string $tableName): array
    {
        return $this->db->createCommand(
            'PRAGMA TABLE_INFO(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')'
        )->queryAll();
    }

    public function rollBackSavepoint(string $name): void
    {
        $this->db->createCommand("ROLLBACK TO SAVEPOINT $name")->execute();
    }

    /**
     * Returns the actual name of a given table name.
     *
     * This method will strip off curly brackets from the given table name and replace the percentage character '%' with
     * {@see ConnectionInterface::tablePrefix}.
     *
     * @param string $name the table name to be converted.
     *
     * @return string the real name of the given table name.
     */
    public function getRawTableName(string $name): string
    {
        if (str_contains($name, '{{')) {
            $name = preg_replace('/{{(.*?)}}/', '\1', $name);

            return str_replace('%', $this->db->getTablePrefix(), $name);
        }

        return $name;
    }

    /**
     * Returns the cache key for the specified table name.
     *
     * @param string $name the table name.
     *
     * @return array the cache key.
     */
    protected function getCacheKey(string $name): array
    {
        return [
            __CLASS__,
            $this->db->getDriver()->getDsn(),
            $this->db->getDriver()->getUsername(),
            $this->getRawTableName($name),
        ];
    }

    /**
     * Returns the cache tag name.
     *
     * This allows {@see refresh()} to invalidate all cached table schemas.
     *
     * @return string the cache tag name.
     */
    protected function getCacheTag(): string
    {
        return md5(serialize([
            __CLASS__,
            $this->db->getDriver()->getDsn(),
            $this->db->getDriver()->getUsername(),
        ]));
    }

    /**
     * Changes row's array key case to lower if PDO one is set to uppercase.
     *
     * @param array $row row's array or an array of row's arrays.
     * @param bool $multiple whether multiple rows or a single row passed.
     *
     * @throws Exception
     *
     * @return array normalized row or rows.
     */
    protected function normalizePdoRowKeyCase(array $row, bool $multiple): array
    {
        if ($this->db->getSlavePdo()?->getAttribute(PDO::ATTR_CASE) !== PDO::CASE_UPPER) {
            return $row;
        }

        if ($multiple) {
            return array_map(static function (array $row) {
                return array_change_key_case($row, CASE_LOWER);
            }, $row);
        }

        return array_change_key_case($row, CASE_LOWER);
    }

    /**
     * @return bool whether this DBMS supports [savepoint](http://en.wikipedia.org/wiki/Savepoint).
     */
    public function supportsSavepoint(): bool
    {
        return $this->db->isSavepointEnabled();
    }

    /**
     * Creates a new savepoint.
     *
     * @param string $name the savepoint name
     *
     * @throws Exception|InvalidConfigException|Throwable
     */
    public function createSavepoint(string $name): void
    {
        $this->db->createCommand("SAVEPOINT $name")->execute();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     *
     * @throws InvalidCallException if the DB connection is not active
     *
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     *
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID(string $sequenceName = ''): string
    {
        $pdo = $this->db->getPDO();

        if ($pdo !== null && $this->db->isActive()) {
            return $pdo->lastInsertId(
                $sequenceName === '' ? null : $this->db->getQuoter()->quoteTableName($sequenceName)
            );
        }

        throw new InvalidCallException('DB Connection is not active.');
    }

    /**
     * Releases an existing savepoint.
     *
     * @param string $name the savepoint name
     *
     * @throws Exception|InvalidConfigException|Throwable
     */
    public function releaseSavepoint(string $name): void
    {
        $this->db->createCommand("RELEASE SAVEPOINT $name")->execute();
    }
}
