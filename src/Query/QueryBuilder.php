<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Query;

use Yiisoft\Db\Connection\Connection;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Conditions\InCondition;
use Yiisoft\Db\Query\Conditions\LikeCondition;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryBuilder as BaseQueryBuilder;
use Yiisoft\Db\Sqlite\Condition\InConditionBuilder;
use Yiisoft\Db\Sqlite\Condition\LikeConditionBuilder;
use Yiisoft\Db\Sqlite\Schema\Schema;
use Yiisoft\Strings\StringHelper;

class QueryBuilder extends BaseQueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    protected array $typeMap = [
        Schema::TYPE_PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UPK => 'integer UNSIGNED PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UBIGPK => 'integer UNSIGNED PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_CHAR => 'char(1)',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'text',
        Schema::TYPE_TINYINT => 'tinyint',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'integer',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DOUBLE => 'double',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'datetime',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'blob',
        Schema::TYPE_BOOLEAN => 'boolean',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];

    /**
     * Contains array of default expression builders. Extend this method and override it, if you want to change default
     * expression builders for this query builder.
     *
     * @return array
     *
     * See {@see \Yiisoft\Db\Expression\ExpressionBuilder} docs for details.
     */
    protected function defaultExpressionBuilders(): array
    {
        return array_merge(parent::defaultExpressionBuilders(), [
            LikeCondition::class => LikeConditionBuilder::class,
            InCondition::class => InConditionBuilder::class,
        ]);
    }

    /**
     * Generates a batch INSERT SQL statement.
     *
     * For example,
     *
     * ```php
     * $connection->createCommand()->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * ```
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array|\Generator $rows the rows to be batch inserted into the table
     * @param array $params
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     *
     * @return string the batch INSERT SQL statement.
     */
    public function batchInsert(string $table, array $columns, $rows, array &$params = []): string
    {
        if (empty($rows)) {
            return '';
        }

        /**
         * SQLite supports batch insert natively since 3.7.11.
         *
         * {@see http://www.sqlite.org/releaselog/3_7_11.html}
         */
        $this->db->open();

        if (\version_compare($this->db->getServerVersion(), '3.7.11', '>=')) {
            return parent::batchInsert($table, $columns, $rows, $params);
        }

        $schema = $this->db->getSchema();

        if (($tableSchema = $schema->getTableSchema($table)) !== null) {
            $columnSchemas = $tableSchema->getColumns();
        } else {
            $columnSchemas = [];
        }

        $values = [];

        foreach ($rows as $row) {
            $vs = [];
            foreach ($row as $i => $value) {
                if (isset($columnSchemas[$columns[$i]])) {
                    $value = $columnSchemas[$columns[$i]]->dbTypecast($value);
                }
                if (\is_string($value)) {
                    $value = $schema->quoteValue($value);
                } elseif (\is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    $value = StringHelper::floatToString($value);
                } elseif ($value === false) {
                    $value = 0;
                } elseif ($value === null) {
                    $value = 'NULL';
                } elseif ($value instanceof ExpressionInterface) {
                    $value = $this->buildExpression($value, $params);
                }
                $vs[] = $value;
            }
            $values[] = \implode(', ', $vs);
        }

        if (empty($values)) {
            return '';
        }

        foreach ($columns as $i => $name) {
            $columns[$i] = $schema->quoteColumnName($name);
        }

        return 'INSERT INTO ' . $schema->quoteTableName($table)
        . ' (' . implode(', ', $columns) . ') SELECT ' . implode(' UNION SELECT ', $values);
    }

    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     *
     * The sequence will be reset such that the primary key of the next new row inserted will have the specified value
     * or 1.
     *
     * @param string $tableName the name of the table whose primary key sequence will be reset.
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set, the next new
     * row's primary key will have a value 1.
     *
     * @throws Exception
     * @throws InvalidArgumentException if the table does not exist or there is no sequence associated with the table.
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws \Throwable
     *
     * @return string the SQL statement for resetting sequence.
     */
    public function resetSequence(string $tableName, $value = null): string
    {
        $db = $this->db;

        $table = $db->getTableSchema($tableName);

        if ($table !== null && $table->getSequenceName() !== null) {
            $tableName = $db->quoteTableName($tableName);
            if ($value === null) {
                $pk = $table->getPrimaryKey();
                $key = $this->db->quoteColumnName(\reset($pk));
                $value = $this->db->useMaster(static function (Connection $db) use ($key, $tableName) {
                    return $db->createCommand("SELECT MAX($key) FROM $tableName")->queryScalar();
                });
            } else {
                $value = (int) $value - 1;
            }

            return "UPDATE sqlite_sequence SET seq='$value' WHERE name='{$table->getName()}'";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: $tableName");
        }

        throw new InvalidArgumentException("There is not sequence associated with table '$tableName'.'");
    }

    /**
     * Enables or disables integrity check.
     *
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Meaningless for SQLite.
     * @param string $table the table name. Meaningless for SQLite.
     *
     * @return string the SQL statement for checking integrity.
     */
    public function checkIntegrity(string $schema = '', string $table = '', bool $check = true): string
    {
        return 'PRAGMA foreign_keys=' . (int) $check;
    }

    /**
     * Builds a SQL statement for truncating a DB table.
     *
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncateTable(string $table): string
    {
        return 'DELETE FROM ' . $this->db->quoteTableName($table);
    }

    /**
     * Builds a SQL statement for dropping an index.
     *
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return string the SQL statement for dropping an index.
     */
    public function dropIndex(string $name, string $table): string
    {
        return 'DROP INDEX ' . $this->db->quoteTableName($name);
    }

    /**
     * Builds a SQL statement for dropping a DB column.
     *
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for dropping a DB column.
     */
    public function dropColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for renaming a column.
     *
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for renaming a DB column.
     */
    public function renameColumn(string $table, string $oldName, string $newName): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     *
     * The method will properly quote the table and column names.
     *
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are
     * multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple
     * columns, separate them with commas or use an array to represent them.
     * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     * SET DEFAULT, SET NULL.
     * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     * SET DEFAULT, SET NULL.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     */
    public function addForeignKey(
        string $name,
        string $table,
        $columns,
        string $refTable,
        $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): string {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     *
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted
     * by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for dropping a foreign key constraint.
     */
    public function dropForeignKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return string the SQL statement for renaming a DB table.
     */
    public function renameTable(string $table, string $newName): string
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' RENAME TO ' . $this->db->quoteTableName($newName);
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     *
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType()]] method will be invoked to convert abstract
     * column type (if any) into the physical one. Anything that is not recognized as abstract type will be kept in the
     * generated SQL. For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become
     * 'varchar(255) not null'.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for changing the definition of a column.
     */
    public function alterColumn(string $table, string $column, string $type): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     *
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     */
    public function addPrimaryKey(string $name, string $table, $columns): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     *
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     *
     * @throws NotSupportedException this is not supported by SQLite.
     *
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     */
    public function dropPrimaryKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for adding an unique constraint to an existing table.
     *
     * @param string $name the name of the unique constraint. The name will be properly quoted by the method.
     * @param string $table the table that the unique constraint will be added to. The name will be properly quoted by
     * the method.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are
     * multiple columns, separate them with commas. The name will be properly quoted by the method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding an unique constraint to an existing table.
     */
    public function addUnique(string $name, string $table, $columns): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for dropping an unique constraint.
     *
     * @param string $name the name of the unique constraint to be dropped. The name will be properly quoted by the
     * method.
     * @param string $table the table whose unique constraint is to be dropped. The name will be properly quoted by the
     * method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for dropping an unique constraint.
     */
    public function dropUnique(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     *
     * @param string $name the name of the check constraint. The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to. The name will be properly quoted by
     * the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding a check constraint to an existing table.
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for dropping a check constraint.
     *
     * @param string $name the name of the check constraint to be dropped. The name will be properly quoted by the
     * method.
     * @param string $table the table whose check constraint is to be dropped. The name will be properly quoted by the
     * method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for dropping a check constraint.
     */
    public function dropCheck(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for adding a default value constraint to an existing table.
     *
     * @param string $name the name of the default value constraint. The name will be properly quoted by the method.
     * @param string $table the table that the default value constraint will be added to. The name will be properly
     * quoted by the method.
     * @param string $column the name of the column to that the constraint will be added on. The name will be properly
     * quoted by the method.
     * @param mixed $value default value.
     *
     * @throws Exception
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     *
     * @return string the SQL statement for adding a default value constraint to an existing table.
     */
    public function addDefaultValue(string $name, string $table, string $column, $value): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Creates a SQL command for dropping a default value constraint.
     *
     * @param string $name the name of the default value constraint to be dropped. The name will be properly quoted by
     * the method.
     * @param string $table the table whose default value constraint is to be dropped. The name will be properly quoted
     * by the method.
     *
     * @throws Exception
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     *
     * @return string the SQL statement for dropping a default value constraint.
     */
    public function dropDefaultValue(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the
     * method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding comment on table.
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the
     * method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     *
     * @throws Exception
     * @throws NotSupportedException
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function dropCommentFromTable(string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @param int|object|null $limit
     * @param int|object|null $offset
     *
     * @return string the LIMIT and OFFSET clauses.
     */
    public function buildLimit($limit, $offset): string
    {
        $sql = '';

        if ($this->hasLimit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->hasOffset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->hasOffset($offset)) {
            /**
             * limit is not optional in SQLite.
             *
             * {@see http://www.sqlite.org/syntaxdiagrams.html#select-stmt}
             */
            $sql = "LIMIT 9223372036854775807 OFFSET $offset"; // 2^63-1
        }

        return $sql;
    }

    /**
     * Generates a SELECT SQL statement from a {@see Query} object.
     *
     * @param Query $query the {@see Query} object from which the SQL statement will be generated.
     * @param array $params the parameters to be bound to the generated SQL statement. These parameters will be included
     * in the result with the additional parameters generated during the query building process.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return array the generated SQL statement (the first array element) and the corresponding parameters to be bound
     * to the SQL statement (the second array element). The parameters returned include those provided in `$params`.
     */
    public function build(Query $query, array $params = []): array
    {
        $query = $query->prepare($this);

        $params = empty($params) ? $query->getParams() : \array_merge($params, $query->getParams());

        $clauses = [
            $this->buildSelect($query->getSelect(), $params, $query->getDistinct(), $query->getSelectOption()),
            $this->buildFrom($query->getFrom(), $params),
            $this->buildJoin($query->getJoin(), $params),
            $this->buildWhere($query->getWhere(), $params),
            $this->buildGroupBy($query->getGroupBy()),
            $this->buildHaving($query->getHaving(), $params),
        ];

        $sql = \implode($this->separator, \array_filter($clauses));
        $sql = $this->buildOrderByAndLimit($sql, $query->getOrderBy(), $query->getLimit(), $query->getOffset());

        if (!empty($query->getOrderBy())) {
            foreach ($query->getOrderBy() as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        if (!empty($query->getGroupBy())) {
            foreach ($query->getGroupBy() as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $union = $this->buildUnion($query->getUnion(), $params);

        if ($union !== '') {
            $sql = "$sql{$this->separator}$union";
        }

        $with = $this->buildWithQueries($query->getWithQueries(), $params);

        if ($with !== '') {
            $sql = "$with{$this->separator}$sql";
        }

        return [$sql, $params];
    }

    /**
     * Builds a SQL statement for creating a new index.
     *
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by
     * the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns,
     * separate them with commas or use an array to represent them. Each column name will be properly quoted by the
     * method, unless a parenthesis is found in the name.
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return string the SQL statement for creating a new index.
     */
    public function createIndex(string $name, string $table, $columns, bool $unique = false): string
    {
        $sql = ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
            . $this->db->quoteTableName($name) . ' ON '
            . $this->db->quoteTableName($table)
            . ' (' . $this->buildColumns($columns) . ')';

        $sql = \preg_replace_callback(
            '/(`.*`) ON ({{(%?)([\w\-]+)}\}\.{{((%?)[\w\-]+)\\}\\})|(`.*`) ON ({{(%?)([\w\-]+)\.([\w\-]+)\\}\\})/',
            static function ($matches) {
                if (!empty($matches[1])) {
                    return $matches[4] . "." . $matches[1]
                     . ' ON {{' . $matches[3] . $matches[5] . '}}';
                }

                if (!empty($matches[7])) {
                    return $matches[10] . '.' . $matches[7]
                     . ' ON {{' . $matches[9] . $matches[11] . '}}';
                }
            },
            $sql
        );

        return $sql;
    }

    /**
     * @param array $unions
     * @param array $params the binding parameters to be populated.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * @return string the UNION clause built from {@see Query::$union}.
     */
    public function buildUnion(array $unions, array &$params = []): string
    {
        if (empty($unions)) {
            return '';
        }

        $result = '';

        foreach ($unions as $i => $union) {
            $query = $union['query'];
            if ($query instanceof Query) {
                [$unions[$i]['query'], $params] = $this->build($query, $params);
            }

            $result .= ' UNION ' . ($union['all'] ? 'ALL ' : '') . ' ' . $unions[$i]['query'];
        }

        return \trim($result);
    }

    /**
     * Creates an SQL statement to insert rows into a database table if they do not already exist (matching unique
     * constraints), or update them if they do.
     *
     * For example,
     *
     * ```php
     * $sql = $queryBuilder->upsert('pages', [
     *     'name' => 'Front page',
     *     'url' => 'http://example.com/', // url is unique
     *     'visits' => 0,
     * ], [
     *     'visits' => new \Yiisoft\Db\Expression('visits + 1'),
     * ], $params);
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of {@see Query} to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array $params the binding parameters that will be generated by this method.
     * They should be bound to the DB command later.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     *
     * @return string the resulting SQL.
     */
    public function upsert(string $table, $insertColumns, $updateColumns, array &$params): string
    {
        /** @var Constraint[] $constraints */
        $constraints = [];

        [$uniqueNames, $insertNames, $updateNames] = $this->prepareUpsertColumns(
            $table,
            $insertColumns,
            $updateColumns,
            $constraints
        );

        if (empty($uniqueNames)) {
            return $this->insert($table, $insertColumns, $params);
        }

        [, $placeholders, $values, $params] = $this->prepareInsertValues($table, $insertColumns, $params);

        $insertSql = 'INSERT OR IGNORE INTO ' . $this->db->quoteTableName($table)
            . (!empty($insertNames) ? ' (' . \implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . \implode(', ', $placeholders) . ')' : $values);

        if ($updateColumns === false) {
            return $insertSql;
        }

        $updateCondition = ['or'];
        $quotedTableName = $this->db->quoteTableName($table);

        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            foreach ($constraint->getColumnNames() as $name) {
                $quotedName = $this->db->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=(SELECT $quotedName FROM `EXCLUDED`)";
            }
            $updateCondition[] = $constraintCondition;
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            foreach ($updateNames as $name) {
                $quotedName = $this->db->quoteColumnName($name);

                if (\strrpos($quotedName, '.') === false) {
                    $quotedName = "(SELECT $quotedName FROM `EXCLUDED`)";
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }

        $updateSql = 'WITH "EXCLUDED" (' . \implode(', ', $insertNames)
            . ') AS (' . (!empty($placeholders) ? 'VALUES (' . \implode(', ', $placeholders) . ')'
            : \ltrim($values, ' ')) . ') ' . $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }
}
