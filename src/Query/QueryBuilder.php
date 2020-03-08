<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Query;

use Yiisoft\Db\Constraints\Constraint;
use Yiisoft\Db\Drivers\Connection;
use Yiisoft\Db\Exceptions\InvalidArgumentException;
use Yiisoft\Db\Exceptions\NotSupportedException;
use Yiisoft\Db\Expressions\Expression;
use Yiisoft\Db\Expressions\ExpressionInterface;
use Yiisoft\Db\Querys\Query;
use Yiisoft\Db\Querys\QueryBuilder as BaseQueryBuilder;
use Yiisoft\Db\Querys\Conditions\LikeCondition;
use Yiisoft\Db\Querys\Conditions\InCondition;
use Yiisoft\Db\Sqlite\Condition\LikeConditionBuilder;
use Yiisoft\Db\Sqlite\Condition\InConditionBuilder;
use Yiisoft\Db\Sqlite\Schema\Schema;
use Yiisoft\Strings\StringHelper;

/**
 * QueryBuilder is the query builder for SQLite databases.
 */
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
     * {@inheritdoc}
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
     *
     * @return string the batch INSERT SQL statement
     */
    public function batchInsert(string $table, array $columns, $rows, array &$params = []): string
    {
        if (empty($rows)) {
            return '';
        }

        // SQLite supports batch insert natively since 3.7.11
        // http://www.sqlite.org/releaselog/3_7_11.html
        $this->db->open(); // ensure pdo is not null

        if (version_compare($this->db->getServerVersion(), '3.7.11', '>=')) {
            return parent::batchInsert($table, $columns, $rows, $params);
        }

        $schema = $this->db->getSchema();

        if (($tableSchema = $schema->getTableSchema($table)) !== null) {
            $columnSchemas = $tableSchema->columns;
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
                if (is_string($value)) {
                    $value = $schema->quoteValue($value);
                } elseif (is_float($value)) {
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
            $values[] = implode(', ', $vs);
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
     * @param string $tableName the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set, the next new
     * row's primary key will have a value 1.
     *
     * @throws InvalidArgumentException if the table does not exist or there is no sequence associated with the table.
     *
     * @return string the SQL statement for resetting sequence
     */
    public function resetSequence(string $tableName, $value = null): string
    {
        $db = $this->db;

        $table = $db->getTableSchema($tableName);

        if ($table !== null && $table->sequenceName !== null) {
            $tableName = $db->quoteTableName($tableName);
            if ($value === null) {
                $key = $this->db->quoteColumnName(reset($table->primaryKey));
                $value = $this->db->useMaster(function (Connection $db) use ($key, $tableName) {
                    return $db->createCommand("SELECT MAX($key) FROM $tableName")->queryScalar();
                });
            } else {
                $value = (int) $value - 1;
            }

            return "UPDATE sqlite_sequence SET seq='$value' WHERE name='{$table->name}'";
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
     * @throws NotSupportedException this is not supported by SQLite
     *
     * @return string the SQL statement for checking integrity
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
     * @throws NotSupportedException this is not supported by SQLite
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
     * @throws NotSupportedException this is not supported by SQLite
     *
     * @return string the SQL statement for renaming a DB column.
     */
    public function renameColumn(string $table, string $oldName, string $newName): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $delete the ON DELETE option. Most DBMS support these options:
     * RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string $update the ON UPDATE option. Most DBMS support these options:
     * RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     *
     * @throws NotSupportedException this is not supported by SQLite
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
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted
     * by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     *
     * @throws NotSupportedException this is not supported by SQLite
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
     * @return string the SQL statement for renaming a DB table.
     */
    public function renameTable(string $table, string $newName): string
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' RENAME TO ' . $this->db->quoteTableName($newName);
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType()]] method will be invoked to convert abstract
     * column type (if any) into the physical one. Anything that is not recognized as abstract type will be kept
     * in the generated SQL. For example, 'string' will be turned into 'varchar(255)', while 'string not null'
     * will become 'varchar(255) not null'.
     *
     * @throws NotSupportedException this is not supported by SQLite
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
     * @throws NotSupportedException this is not supported by SQLite
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
     * @throws NotSupportedException this is not supported by SQLite
     *
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     */
    public function dropPrimaryKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function addUnique(string $name, string $table, $columns): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function dropUnique(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function dropCheck(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function addDefaultValue(string $name, string $table, string $column, $value): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function dropDefaultValue(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException
     */
    public function dropCommentFromTable(string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * {@inheritdoc}
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
            // limit is not optional in SQLite
            // http://www.sqlite.org/syntaxdiagrams.html#select-stmt
            $sql = "LIMIT 9223372036854775807 OFFSET $offset"; // 2^63-1
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function build(Query $query, array $params = []): array
    {
        $query = $query->prepare($this);

        $params = empty($params) ? $query->getParams() : array_merge($params, $query->getParams());

        $clauses = [
            $this->buildSelect($query->getSelect(), $params, $query->getDistinct(), $query->getSelectOption()),
            $this->buildFrom($query->getFrom(), $params),
            $this->buildJoin($query->getJoin(), $params),
            $this->buildWhere($query->getWhere(), $params),
            $this->buildGroupBy($query->getGroupBy()),
            $this->buildHaving($query->getHaving(), $params),
        ];

        $sql = implode($this->separator, array_filter($clauses));
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

    public function createIndex(string $name, string $table, $columns, bool $unique = false): string
    {
        $sql = ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
            . $this->db->quoteTableName($name) . ' ON '
            . $this->db->quoteTableName($table)
            . ' (' . $this->buildColumns($columns) . ')';

        $sql = preg_replace_callback(
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
     * @param array $params the binding parameters to be populated
     *
     * @return string the UNION clause built from {@see Query::$union}.
     */
    public function buildUnion(array $unions, array &$params): string
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

        return trim($result);
    }

    /**
     * {@inheritdoc}
     * @see https://stackoverflow.com/questions/15277373/sqlite-upsert-update-or-insert/15277374#15277374
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
            . (!empty($insertNames) ? ' (' . implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : $values);

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

                if (strrpos($quotedName, '.') === false) {
                    $quotedName = "(SELECT $quotedName FROM `EXCLUDED`)";
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }

        $updateSql = 'WITH "EXCLUDED" (' . implode(', ', $insertNames)
            . ') AS (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' '))
            . ') ' . $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }
}
