<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Schema;

use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Constraints\CheckConstraint;
use Yiisoft\Db\Constraints\Constraint;
use Yiisoft\Db\Constraints\ConstraintFinderInterface;
use Yiisoft\Db\Constraints\ConstraintFinderTrait;
use Yiisoft\Db\Constraints\ForeignKeyConstraint;
use Yiisoft\Db\Constraints\IndexConstraint;
use Yiisoft\Db\Exceptions\NotSupportedException;
use Yiisoft\Db\Expressions\Expression;
use Yiisoft\Db\Schemas\Schema as AbstractSchema;
use Yiisoft\Db\Schemas\ColumnSchema;
use Yiisoft\Db\Schemas\TableSchema;
use Yiisoft\Db\Sqlite\Query\QueryBuilder;
use Yiisoft\Db\Sqlite\Token\SqlToken;
use Yiisoft\Db\Sqlite\Token\SqlTokenizer;
use Yiisoft\Db\Transactions\Transaction;

/**
 * Schema is the class for retrieving metadata from a SQLite (2/3) database.
 *
 * @property string $transactionIsolationLevel The transaction isolation level to use for this transaction.
 * This can be either {@see Transaction::READ_UNCOMMITTED} or {@see Transaction::SERIALIZABLE}.
 */
class Schema extends AbstractSchema implements ConstraintFinderInterface
{
    use ConstraintFinderTrait;

    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public array $typeMap = [
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
     * {@inheritdoc}
     */
    protected string $tableQuoteCharacter = '`';

    /**
     * {@inheritdoc}
     */
    protected string $columnQuoteCharacter = '`';

    /**
     * {@inheritdoc}
     */
    protected function findTableNames($schema = '')
    {
        $sql = "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name";

        return $this->db->createCommand($sql)->queryColumn();
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableSchema(string $name): ?TableSchema
    {
        $table = new TableSchema();

        $table->name = $name;
        $table->fullName = $name;

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTablePrimaryKey($tableName)
    {
        return $this->loadTableConstraints($tableName, 'primaryKey');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableForeignKeys($tableName)
    {
        $foreignKeys = $this->db->createCommand(
            'PRAGMA FOREIGN_KEY_LIST (' . $this->quoteValue($tableName) . ')'
        )->queryAll();

        $foreignKeys = $this->normalizePdoRowKeyCase($foreignKeys, true);

        $foreignKeys = ArrayHelper::index($foreignKeys, null, 'table');

        ArrayHelper::multisort($foreignKeys, 'seq', SORT_ASC, SORT_NUMERIC);

        $result = [];

        foreach ($foreignKeys as $table => $foreignKey) {
            $fk = new ForeignKeyConstraint();

            $fk->setColumnNames(ArrayHelper::getColumn($foreignKey, 'from'));
            $fk->setForeignTableName($table);
            $fk->setForeignColumnNames(ArrayHelper::getColumn($foreignKey, 'to'));
            $fk->setOnDelete($foreignKey[0]['on_delete'] ?? null);
            $fk->setOnUpdate($foreignKey[0]['on_update'] ?? null);

            $result[] = $fk;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableIndexes($tableName)
    {
        return $this->loadTableConstraints($tableName, 'indexes');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableUniques($tableName)
    {
        return $this->loadTableConstraints($tableName, 'uniques');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableChecks($tableName)
    {
        $sql = $this->db->createCommand('SELECT `sql` FROM `sqlite_master` WHERE name = :tableName', [
            ':tableName' => $tableName,
        ])->queryScalar();

        /** @var $code SqlToken[]|SqlToken[][]|SqlToken[][][] */
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

            if (isset($createTableToken[$firstMatchIndex - 2]) && $createTableToken->matches($pattern, $firstMatchIndex - 2)) {
                $name = $createTableToken[$firstMatchIndex - 1]->content;
            }

            $ck = new CheckConstraint();
            $ck->setName($name);
            $ck->setExpression($checkSql);

            $result[] = $ck;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException if this method is called.
     */
    protected function loadTableDefaultValues($tableName)
    {
        throw new NotSupportedException('SQLite does not support default value constraints.');
    }

    /**
     * Creates a query builder for the MySQL database.
     *
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     *
     * @return QueryBuilder query builder instance
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

    /**
     * {@inheritdoc}
     *
     * @return ColumnSchemaBuilder column schema builder instance
     */
    public function createColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchema $table the table metadata
     *
     * @return bool whether the table exists in the database
     */
    protected function findColumns($table): bool
    {
        $sql = 'PRAGMA table_info(' . $this->quoteSimpleTableName($table->name) . ')';
        $columns = $this->db->createCommand($sql)->queryAll();

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $info) {
            $column = $this->loadColumnSchema($info);
            $table->columns[$column->name] = $column;
            if ($column->isPrimaryKey) {
                $table->primaryKey[] = $column->name;
            }
        }

        if (count($table->primaryKey) === 1 && !strncasecmp($table->columns[$table->primaryKey[0]]->dbType, 'int', 3)) {
            $table->sequenceName = '';
            $table->columns[$table->primaryKey[0]]->autoIncrement = true;
        }

        return true;
    }

    /**
     * Collects the foreign key column details for the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $sql = 'PRAGMA foreign_key_list(' . $this->quoteSimpleTableName($table->name) . ')';
        $keys = $this->db->createCommand($sql)->queryAll();

        foreach ($keys as $key) {
            $id = (int) $key['id'];
            if (!isset($table->foreignKeys[$id])) {
                $table->foreignKeys[$id] = [$key['table'], $key['from'] => $key['to']];
            } else {
                // composite FK
                $table->foreignKeys[$id][$key['from']] = $key['to'];
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
     * @param TableSchema $table the table metadata
     *
     * @return array all unique indexes for the given table.
     */
    public function findUniqueIndexes($table)
    {
        $sql = 'PRAGMA index_list(' . $this->quoteSimpleTableName($table->name) . ')';
        $indexes = $this->db->createCommand($sql)->queryAll();
        $uniqueIndexes = [];

        foreach ($indexes as $index) {
            $indexName = $index['name'];
            $indexInfo = $this->db->createCommand(
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
     * @param array $info column information
     *
     * @return ColumnSchema the column schema object
     */
    protected function loadColumnSchema($info): ColumnSchema
    {
        $column = $this->createColumnSchema();
        $column->name = $info['name'];
        $column->allowNull = !$info['notnull'];
        $column->isPrimaryKey = $info['pk'] != 0;
        $column->dbType = strtolower($info['type']);
        $column->unsigned = strpos($column->dbType, 'unsigned') !== false;
        $column->type = self::TYPE_STRING;

        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/', $column->dbType, $matches)) {
            $type = strtolower($matches[1]);

            if (isset($this->typeMap[$type])) {
                $column->type = $this->typeMap[$type];
            }

            if (!empty($matches[2])) {
                $values = explode(',', $matches[2]);
                $column->size = $column->precision = (int) $values[0];
                if (isset($values[1])) {
                    $column->scale = (int) $values[1];
                }
                if ($column->size === 1 && ($type === 'tinyint' || $type === 'bit')) {
                    $column->type = 'boolean';
                } elseif ($type === 'bit') {
                    if ($column->size > 32) {
                        $column->type = 'bigint';
                    } elseif ($column->size === 32) {
                        $column->type = 'integer';
                    }
                }
            }
        }

        $column->phpType = $this->getColumnPhpType($column);

        if (!$column->isPrimaryKey) {
            if ($info['dflt_value'] === 'null' || $info['dflt_value'] === '' || $info['dflt_value'] === null) {
                $column->defaultValue = null;
            } elseif ($column->type === 'timestamp' && $info['dflt_value'] === 'CURRENT_TIMESTAMP') {
                $column->defaultValue = new Expression('CURRENT_TIMESTAMP');
            } else {
                $value = trim($info['dflt_value'], "'\"");
                $column->defaultValue = $column->phpTypecast($value);
            }
        }

        return $column;
    }

    /**
     * Sets the isolation level of the current transaction.
     *
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be either {@see Transaction::READ_UNCOMMITTED} or {@see Transaction::SERIALIZABLE}.
     *
     * @throws NotSupportedException when unsupported isolation levels are used.
     * SQLite only supports SERIALIZABLE and READ UNCOMMITTED.
     *
     * {@see http://www.sqlite.org/pragma.html#pragma_read_uncommitted}
     */
    public function setTransactionIsolationLevel($level): void
    {
        switch ($level) {
            case Transaction::SERIALIZABLE:
                $this->db->createCommand('PRAGMA read_uncommitted = False;')->execute();
                break;
            case Transaction::READ_UNCOMMITTED:
                $this->db->createCommand('PRAGMA read_uncommitted = True;')->execute();
                break;
            default:
                throw new NotSupportedException(
                    get_class($this) . ' only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
                );
        }
    }

    /**
     * Returns table columns info.
     *
     * @param string $tableName table name
     *
     * @return array
     */
    private function loadTableColumnsInfo($tableName): array
    {
        $tableColumns = $this->db->createCommand(
            'PRAGMA TABLE_INFO (' . $this->quoteValue($tableName) . ')'
        )->queryAll();

        $tableColumns = $this->normalizePdoRowKeyCase($tableColumns, true);

        return ArrayHelper::index($tableColumns, 'cid');
    }

    /**
     * Loads multiple types of constraints and returns the specified ones.
     *
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - primaryKey
     * - indexes
     * - uniques
     *
     * @return mixed constraints.
     */
    private function loadTableConstraints($tableName, $returnType)
    {
        $indexes = $this->db->createCommand('PRAGMA INDEX_LIST (' . $this->quoteValue($tableName) . ')')->queryAll();
        $indexes = $this->normalizePdoRowKeyCase($indexes, true);
        $tableColumns = null;

        if (!empty($indexes) && !isset($indexes[0]['origin'])) {
            /*
             * SQLite may not have an "origin" column in INDEX_LIST
             * See https://www.sqlite.org/src/info/2743846cdba572f6
             */
            $tableColumns = $this->loadTableColumnsInfo($tableName);
        }

        $result = [
            'primaryKey' => null,
            'indexes' => [],
            'uniques' => [],
        ];

        foreach ($indexes as $index) {
            $columns = $this->db->createCommand(
                'PRAGMA INDEX_INFO (' . $this->quoteValue($index['name']) . ')'
            )->queryAll();

            $columns = $this->normalizePdoRowKeyCase($columns, true);

            ArrayHelper::multisort($columns, 'seqno', SORT_ASC, SORT_NUMERIC);

            if ($tableColumns !== null) {
                // SQLite may not have an "origin" column in INDEX_LIST
                $index['origin'] = 'c';
                if (!empty($columns) && $tableColumns[$columns[0]['cid']]['pk'] > 0) {
                    $index['origin'] = 'pk';
                } elseif ($index['unique'] && $this->isSystemIdentifier($index['name'])) {
                    $index['origin'] = 'u';
                }
            }

            $ic = new IndexConstraint();

            $ic->setIsPrimary($index['origin'] === 'pk');
            $ic->setIsUnique((bool) $index['unique']);
            $ic->setName($index['name']);
            $ic->setColumnNames(ArrayHelper::getColumn($columns, 'name'));

            $result['indexes'][] = $ic;

            if ($index['origin'] === 'u') {
                $ct = new Constraint();

                $ct->setName($index['name']);
                $ct->setColumnNames(ArrayHelper::getColumn($columns, 'name'));

                $result['uniques'][] = $ct;
            } elseif ($index['origin'] === 'pk') {
                $ct = new Constraint();

                $ct->setColumnNames(ArrayHelper::getColumn($columns, 'name'));

                $result['primaryKey'] = $ct;
            }
        }

        if ($result['primaryKey'] === null) {
            /*
             * Additional check for PK in case of INTEGER PRIMARY KEY with ROWID
             * See https://www.sqlite.org/lang_createtable.html#primkeyconst
             */

            if ($tableColumns === null) {
                $tableColumns = $this->loadTableColumnsInfo($tableName);
            }

            foreach ($tableColumns as $tableColumn) {
                if ($tableColumn['pk'] > 0) {
                    $ct = new Constraint();
                    $ct->setColumnNames([$tableColumn['name']]);

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
     * Return whether the specified identifier is a SQLite system identifier.
     *
     * @param string $identifier
     *
     * @return bool
     *
     * {@see https://www.sqlite.org/src/artifact/74108007d286232f}
     */
    private function isSystemIdentifier($identifier): bool
    {
        return strncmp($identifier, 'sqlite_', 7) === 0;
    }
}
