<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Throwable;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Arrays\ArraySorter;
use Yiisoft\Db\Constraint\CheckConstraint;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Constraint\ForeignKeyConstraint;
use Yiisoft\Db\Constraint\IndexConstraint;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Schema\ColumnSchema;
use Yiisoft\Db\Schema\Schema as AbstractSchema;
use Yiisoft\Db\Transaction\Transaction;

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
 * either {@see Transaction::READ_UNCOMMITTED} or {@see Transaction::SERIALIZABLE}.
 */
final class Schema extends AbstractSchema
{
    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
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

    /**
     * @var string|string[] character used to quote schema, table, etc. names. An array of 2 characters can be used in
     * case starting and ending characters are different.
     */
    protected $tableQuoteCharacter = '`';

    /**
     * @var string|string[] character used to quote column names. An array of 2 characters can be used in case starting
     * and ending characters are different.
     */
    protected $columnQuoteCharacter = '`';

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
        $sql = "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name";

        return $this->getDb()->createCommand($sql)->queryColumn();
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
        return $this->loadTableConstraints($tableName, 'primaryKey');
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
        $foreignKeys = $this->getDb()->createCommand(
            'PRAGMA FOREIGN_KEY_LIST (' . $this->quoteValue($tableName) . ')'
        )->queryAll();

        $foreignKeys = $this->normalizePdoRowKeyCase($foreignKeys, true);

        $foreignKeys = ArrayHelper::index($foreignKeys, null, 'table');

        ArraySorter::multisort($foreignKeys, 'seq', SORT_ASC, SORT_NUMERIC);

        $result = [];

        foreach ($foreignKeys as $table => $foreignKey) {
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
     * @return IndexConstraint[] indexes for the given table.
     */
    protected function loadTableIndexes(string $tableName): array
    {
        return $this->loadTableConstraints($tableName, 'indexes');
    }

    /**
     * Loads all unique constraints for the given table.
     *
     * @param string $tableName table name.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|Throwable
     *
     * @return Constraint[] unique constraints for the given table.
     */
    protected function loadTableUniques(string $tableName): array
    {
        return $this->loadTableConstraints($tableName, 'uniques');
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
        $sql = $this->getDb()->createCommand('SELECT `sql` FROM `sqlite_master` WHERE name = :tableName', [
            ':tableName' => $tableName,
        ])->queryScalar();

        /** @var SqlToken[]|SqlToken[][]|SqlToken[][][] $code */
        $code = (new SqlTokenizer($sql))->tokenize();

        $pattern = (new SqlTokenizer('any CREATE any TABLE any()'))->tokenize();

        if (!$code[0]->matches($pattern, 0, $firstMatchIndex, $lastMatchIndex)) {
            return [];
        }

        $createTableToken = $code[0][$lastMatchIndex - 1];
        $result = [];
        $offset = 0;

        while (true) {
            $pattern = (new SqlTokenizer('any CHECK()'))->tokenize();

            if (!$createTableToken->matches($pattern, $offset, $firstMatchIndex, $offset)) {
                break;
            }

            $checkSql = $createTableToken[$offset - 1]->getSql();
            $name = null;
            $pattern = (new SqlTokenizer('CONSTRAINT any'))->tokenize();

            if (
                isset($createTableToken[$firstMatchIndex - 2])
                && $createTableToken->matches($pattern, $firstMatchIndex - 2)
            ) {
                $name = $createTableToken[$firstMatchIndex - 1]->getContent();
            }

            $ck = (new CheckConstraint())
                ->name($name)
                ->expression($checkSql);

            $result[] = $ck;
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
     * Creates a query builder for the MySQL database.
     *
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     *
     * @return QueryBuilder query builder instance.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->getDb());
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
        $sql = 'PRAGMA table_info(' . $this->quoteSimpleTableName($table->getName()) . ')';
        $columns = $this->getDb()->createCommand($sql)->queryAll();

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $info) {
            $column = $this->loadColumnSchema($info);
            $table->columns($column->getName(), $column);
            if ($column->isPrimaryKey()) {
                $table->primaryKey($column->getName());
            }
        }

        $pk = $table->getPrimaryKey();
        if (count($pk) === 1 && !strncasecmp($table->getColumn($pk[0])->getDbType(), 'int', 3)) {
            $table->sequenceName('');
            $table->getColumn($pk[0])->autoIncrement(true);
        }

        return true;
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
        $sql = 'PRAGMA foreign_key_list(' . $this->quoteSimpleTableName($table->getName()) . ')';
        $keys = $this->getDb()->createCommand($sql)->queryAll();

        foreach ($keys as $key) {
            $id = (int) $key['id'];
            $fk = $table->getForeignKeys();
            if (!isset($fk[$id])) {
                $table->foreignKey($id, ([$key['table'], $key['from'] => $key['to']]));
            } else {
                /** composite FK */
                $table->compositeFK($id, $key['from'], $key['to']);
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
        $sql = 'PRAGMA index_list(' . $this->quoteSimpleTableName($table->getName()) . ')';
        $indexes = $this->getDb()->createCommand($sql)->queryAll();
        $uniqueIndexes = [];

        foreach ($indexes as $index) {
            $indexName = $index['name'];
            $indexInfo = $this->getDb()->createCommand(
                'PRAGMA index_info(' . $this->quoteValue($index['name']) . ')'
            )->queryAll();

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
     */
    protected function loadColumnSchema(array $info): ColumnSchema
    {
        $column = $this->createColumnSchema();
        $column->name($info['name']);
        $column->allowNull(!$info['notnull']);
        $column->primaryKey($info['pk'] != 0);
        $column->dbType(strtolower($info['type']));
        $column->unsigned(strpos($column->getDbType(), 'unsigned') !== false);
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
     * {@see Transaction::READ_UNCOMMITTED} or {@see Transaction::SERIALIZABLE}.
     *
     * @throws Exception|InvalidConfigException|NotSupportedException|Throwable when unsupported isolation levels are
     * used. SQLite only supports SERIALIZABLE and READ UNCOMMITTED.
     *
     * {@see http://www.sqlite.org/pragma.html#pragma_read_uncommitted}
     */
    public function setTransactionIsolationLevel(string $level): void
    {
        switch ($level) {
            case Transaction::SERIALIZABLE:
                $this->getDb()->createCommand('PRAGMA read_uncommitted = False;')->execute();
                break;
            case Transaction::READ_UNCOMMITTED:
                $this->getDb()->createCommand('PRAGMA read_uncommitted = True;')->execute();
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
        $tableColumns = $this->getDb()->createCommand(
            'PRAGMA TABLE_INFO (' . $this->quoteValue($tableName) . ')'
        )->queryAll();

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
     * @return mixed constraints.
     */
    private function loadTableConstraints(string $tableName, string $returnType)
    {
        $tableColumns = null;
        $indexList = $this->getDb()->createCommand(
            'PRAGMA INDEX_LIST (' . $this->quoteValue($tableName) . ')'
        )->queryAll();
        $indexes = $this->normalizePdoRowKeyCase($indexList, true);

        if (!empty($indexes) && !isset($indexes[0]['origin'])) {
            /**
             * SQLite may not have an "origin" column in INDEX_LIST.
             *
             * {See https://www.sqlite.org/src/info/2743846cdba572f6}
             */
            $tableColumns = $this->loadTableColumnsInfo($tableName);
        }

        $result = [
            'primaryKey' => null,
            'indexes' => [],
            'uniques' => [],
        ];

        foreach ($indexes as $index) {
            $columns = $this->getPragmaIndexInfo($index['name']);

            if ($tableColumns !== null) {
                /** SQLite may not have an "origin" column in INDEX_LIST */
                $index['origin'] = 'c';

                if (!empty($columns) && $tableColumns[$columns[0]['cid']]['pk'] > 0) {
                    $index['origin'] = 'pk';
                }
            }

            $ic = (new IndexConstraint())
                ->primary($index['origin'] === 'pk')
                ->unique((bool) $index['unique'])
                ->name($index['name'])
                ->columnNames(ArrayHelper::getColumn($columns, 'name'));

            $result['indexes'][] = $ic;

            if ($index['origin'] === 'pk') {
                $ct = (new Constraint())
                    ->columnNames(ArrayHelper::getColumn($columns, 'name'));

                $result['primaryKey'] = $ct;
            } elseif ($index['unique']) {
                $ct = (new Constraint())
                    ->name($index['name'])
                    ->columnNames(ArrayHelper::getColumn($columns, 'name'));

                $result['uniques'][] = $ct;
            }
        }

        if ($result['primaryKey'] === null) {
            /**
             * Additional check for PK in case of INTEGER PRIMARY KEY with ROWID.
             *
             * {@See https://www.sqlite.org/lang_createtable.html#primkeyconst}
             */

            if ($tableColumns === null) {
                $tableColumns = $this->loadTableColumnsInfo($tableName);
            }

            foreach ($tableColumns as $tableColumn) {
                if ($tableColumn['pk'] > 0) {
                    $ct = (new Constraint())
                        ->columnNames([$tableColumn['name']]);

                    $result['primaryKey'] = $ct;
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
    private function getPragmaIndexInfo(string $name): array
    {
        $column = $this->getDb()->createCommand('PRAGMA INDEX_INFO (' . $this->quoteValue($name) . ')')->queryAll();
        $columns = $this->normalizePdoRowKeyCase($column, true);
        ArraySorter::multisort($columns, 'seqno', SORT_ASC, SORT_NUMERIC);

        return $columns;
    }
}
