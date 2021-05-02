<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Generator;
use JsonException;
use Throwable;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\InvalidParamException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionBuilder;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Conditions\InCondition;
use Yiisoft\Db\Query\Conditions\LikeCondition;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryBuilder as BaseQueryBuilder;
use Yiisoft\Db\Sqlite\Condition\InConditionBuilder;
use Yiisoft\Db\Sqlite\Condition\LikeConditionBuilder;
use Yiisoft\Strings\NumericHelper;

use function array_column;
use function array_filter;
use function array_merge;
use function implode;
use function is_float;
use function is_string;
use function ltrim;
use function reset;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function version_compare;

final class QueryBuilder extends BaseQueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    protected array $typeMap = [
        Schema::TYPE_PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UBIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
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
     * See {@see ExpressionBuilder} docs for details.
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
     * @param array|Generator $rows the rows to be batch inserted into the table
     * @param array $params
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
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
        $this->getDb()->open();

        if (version_compare($this->getDb()->getServerVersion(), '3.7.11', '>=')) {
            return parent::batchInsert($table, $columns, $rows, $params);
        }

        $schema = $this->getDb()->getSchema();

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
                if (is_string($value)) {
                    $value = $schema->quoteValue($value);
                } elseif (is_float($value)) {
                    /** ensure type cast always has . as decimal separator in all locales */
                    $value = NumericHelper::normalize($value);
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
     * @param string $tableName the name of the table whose primary key sequence will be reset.
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set, the next new
     * row's primary key will have a value 1.
     *
     * @throws Exception|InvalidArgumentException|Throwable if the table does not exist or there is no sequence
     * associated with the table.
     *
     * @return string the SQL statement for resetting sequence.
     */
    public function resetSequence(string $tableName, $value = null): string
    {
        $db = $this->getDb();

        $table = $db->getTableSchema($tableName);

        if ($table !== null && $table->getSequenceName() !== null) {
            $tableName = $db->quoteTableName($tableName);
            if ($value === null) {
                $pk = $table->getPrimaryKey();
                $key = $this->getDb()->quoteColumnName(reset($pk));
                $value = $this->getDb()->useMaster(static function (Connection $db) use ($key, $tableName) {
                    return $db->createCommand("SELECT MAX($key) FROM $tableName")->queryScalar();
                });
            } else {
                $value = (int) $value - 1;
            }

            return "UPDATE sqlite_sequence SET seq='$value' WHERE name='{$table->getName()}'";
        }

        if ($table === null) {
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
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncateTable(string $table): string
    {
        return 'DELETE FROM ' . $this->getDb()->quoteTableName($table);
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
        return 'DROP INDEX ' . $this->getDb()->quoteTableName($name);
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
     * @param array|string $columns the name of the column to that the constraint will be added on. If there are
     * multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param array|string $refColumns the name of the column that the foreign key references to. If there are multiple
     * columns, separate them with commas or use an array to represent them.
     * @param string|null $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     * SET DEFAULT, SET NULL.
     * @param string|null $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     * SET DEFAULT, SET NULL.
     *
     * @throws Exception|InvalidParamException
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
        $schema = $refschema = '';

        if (($pos = strpos($table, '.')) !== false) {
            $schema = $this->unquoteTableName(substr($table, 0, $pos));
            $table = substr($table, $pos + 1);
        }

        if (($pos_ref = strpos($refTable, '.')) !== false) {
            $refschema = substr($refTable, 0, $pos_ref);
            $refTable = substr($refTable, $pos_ref + 1);
        }

        if (($schema !== '' || ($refschema !== '' && $schema !== $refschema))) {
            return '' ;
        }

        /** @psalm-suppress TypeDoesNotContainType */
        if ($schema !== '') {
            $tmp_table_name = "temp_{$schema}_" . $this->unquoteTableName($table);
            $schema .= '.';
            $unquoted_tablename = $schema . $this->unquoteTableName($table);
            $quoted_tablename = $schema . $this->getDb()->quoteTableName($table);
        } else {
            $unquoted_tablename = $this->unquoteTableName($table);
            $quoted_tablename = $this->getDb()->quoteTableName($table);
            $tmp_table_name = 'temp_' . $this->unquoteTableName($table);
        }

        $fields_definitions_tokens = $this->getFieldDefinitionsTokens($unquoted_tablename);
        $ddl_fields_defs = $fields_definitions_tokens->getSql();
        $ddl_fields_defs .= ",\nCONSTRAINT " . $this->getDb()->quoteColumnName($name) . ' FOREIGN KEY (' .
            implode(',', (array)$columns) . ") REFERENCES $refTable(" . implode(',', (array)$refColumns) . ')';

        if ($update !== null) {
            $ddl_fields_defs .= " ON UPDATE $update";
        }

        if ($delete !== null) {
            $ddl_fields_defs .= " ON DELETE $delete";
        }

        $foreign_keys_state = $this->foreignKeysState();
        $return_queries = [];
        $return_queries[] = 'PRAGMA foreign_keys = off';
        $return_queries[] = "SAVEPOINT add_foreign_key_to_$tmp_table_name";
        $return_queries[] = 'CREATE TEMP TABLE ' . $this->getDb()->quoteTableName($tmp_table_name)
            . " AS SELECT * FROM $quoted_tablename";
        $return_queries[] = "DROP TABLE $quoted_tablename";
        $return_queries[] = "CREATE TABLE $quoted_tablename (" . trim($ddl_fields_defs, " \n\r\t,") . ')';
        $return_queries[] = "INSERT INTO $quoted_tablename SELECT * FROM " . $this->getDb()->quoteTableName($tmp_table_name);
        $return_queries[] = 'DROP TABLE ' . $this->getDb()->quoteTableName($tmp_table_name);
        $return_queries = array_merge($return_queries, $this->getIndexSqls($unquoted_tablename));

        $return_queries[] = "RELEASE add_foreign_key_to_$tmp_table_name";
        $return_queries[] = "PRAGMA foreign_keys = $foreign_keys_state";

        return implode(';', $return_queries);
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     *
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted
     * by the method.
     * @param string $table
     *
     * @throws Exception|InvalidParamException|NotSupportedException
     *
     * @return string the SQL statement for dropping a foreign key constraint.
     */
    public function dropForeignKey(string $name, string $table): string
    {
        $return_queries = [];
        $ddl_fields_def = '';
        $sql_fields_to_insert = [];
        $skipping = false;
        $foreign_found = false;
        $quoted_foreign_name = $this->getDb()->quoteColumnName($name);

        $quoted_tablename = $this->getDb()->quoteTableName($table);
        $unquoted_tablename = $this->unquoteTableName($table);

        $fields_definitions_tokens = $this->getFieldDefinitionsTokens($unquoted_tablename);

        $offset = 0;
        $constraint_pos = 0;

        /** Traverse the tokens looking for either an identifier (field name) or a foreign key */
        while ($fields_definitions_tokens->offsetExists($offset)) {
            $token = $fields_definitions_tokens[$offset++];

            /**
             * These searchs could be done with another SqlTokenizer, but I don't konw how to do them, the documentation
             * for sqltokenizer si really scarse.
             */
            $tokenType = $token->getType();

            if ($tokenType === SqlToken::TYPE_IDENTIFIER) {
                $identifier = (string) $token;
                $sql_fields_to_insert[] = $identifier;
            } elseif ($tokenType === SqlToken::TYPE_KEYWORD) {
                $keyword = (string) $token;

                if ($keyword === 'CONSTRAINT' || $keyword === 'FOREIGN') {
                    /** Constraint key found */
                    $other_offset = $offset;

                    if ($keyword === 'CONSTRAINT') {
                        $constraint_name = $this->getDb()->quoteColumnName(
                            $fields_definitions_tokens[$other_offset]->getContent()
                        );
                    } else {
                        $constraint_name = $this->getDb()->quoteColumnName((string) $constraint_pos);
                    }

                    /** @psalm-suppress TypeDoesNotContainType */
                    if (($constraint_name === $quoted_foreign_name) || (is_int($name) && $constraint_pos === $name)) {
                        /** Found foreign key $name, skip it */
                        $foreign_found = true;
                        $skipping = true;
                        $offset = $other_offset;
                    }
                    $constraint_pos++;
                }
            } else {
                throw new NotSupportedException("Unexpected: $token");
            }

            if (!$skipping) {
                $ddl_fields_def .= $token . ' ';
            }

            /** Skip or keep until the next */
            while ($fields_definitions_tokens->offsetExists($offset)) {
                $skip_token = $fields_definitions_tokens[$offset];
                $skip_next = $fields_definitions_tokens[$offset + 1];

                if (!$skipping) {
                    $ddl_fields_def .= (string) $skip_token . ($skip_next == ',' ? '' : ' ');
                }

                $skipTokenType = $skip_token->getType();

                if ($skipTokenType === SqlToken::TYPE_OPERATOR && $skip_token == ',') {
                    $ddl_fields_def .= "\n";
                    ++$offset;
                    $skipping = false;
                    break;
                }

                ++$offset;
            }
        }

        if (!$foreign_found) {
            throw new InvalidParamException("foreign key constraint '$name' not found in table '$table'");
        }

        $foreign_keys_state = $this->foreignKeysState();

        $return_queries[] = 'PRAGMA foreign_keys = 0';
        $return_queries[] = "SAVEPOINT drop_column_$unquoted_tablename";
        $return_queries[] = 'CREATE TABLE ' . $this->getDb()->quoteTableName("temp_$unquoted_tablename")
            . " AS SELECT * FROM $quoted_tablename";
        $return_queries[] = "DROP TABLE $quoted_tablename";
        $return_queries[] = "CREATE TABLE $quoted_tablename (" . trim($ddl_fields_def, " \n\r\t,") . ')';
        $return_queries[] = "INSERT INTO $quoted_tablename SELECT " . implode(',', $sql_fields_to_insert) . ' FROM '
             . $this->getDb()->quoteTableName("temp_$unquoted_tablename");
        $return_queries[] = 'DROP TABLE ' . $this->getDb()->quoteTableName("temp_$unquoted_tablename");
        $return_queries = array_merge($return_queries, $this->getIndexSqls($unquoted_tablename));
        $return_queries[] = "RELEASE drop_column_$unquoted_tablename";
        $return_queries[] = "PRAGMA foreign_keys = $foreign_keys_state";

        return implode(';', $return_queries);
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $oldName the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB table.
     */
    public function renameTable(string $oldName, string $newName): string
    {
        return 'ALTER TABLE ' . $this->getDb()->quoteTableName($oldName) . ' RENAME TO ' . $this->getDb()->quoteTableName($newName);
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
     * @param array|string $columns comma separated string or array of columns that the primary key will consist of.
     *
     * @throws Exception|InvalidParamException
     *
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     */
    public function addPrimaryKey(string $name, string $table, $columns): string
    {
        $return_queries = [];
        $schema = '';

        if (($pos = strpos($table, '.')) !== false) {
            $schema = $this->unquoteTableName(substr($table, 0, $pos));
            $table = substr($table, $pos + 1);
            $unquoted_tablename = $schema . '.' . $this->unquoteTableName($table);
            $quoted_tablename = $schema . '.' . $this->getDb()->quoteTableName($table);
            $tmp_table_name = "temp_{$schema}_" . $this->unquoteTableName($table);
        } else {
            $unquoted_tablename = $this->unquoteTableName($table);
            $quoted_tablename = $this->getDb()->quoteTableName($table);
            $tmp_table_name = 'temp_' . $this->unquoteTableName($table);
        }

        $fields_definitions_tokens = $this->getFieldDefinitionsTokens($unquoted_tablename);
        $ddl_fields_defs = $fields_definitions_tokens->getSql();
        $ddl_fields_defs .= ', CONSTRAINT ' . $this->getDb()->quoteColumnName($name) . ' PRIMARY KEY (' .
            implode(',', (array)$columns) . ')';
        $foreign_keys_state = $this->foreignKeysState();
        $return_queries[] = 'PRAGMA foreign_keys = 0';
        $return_queries[] = "SAVEPOINT add_primary_key_to_$tmp_table_name";
        $return_queries[] = 'CREATE TABLE ' . $this->getDb()->quoteTableName($tmp_table_name) .
            " AS SELECT * FROM $quoted_tablename";
        $return_queries[] = "DROP TABLE $quoted_tablename";
        $return_queries[] = "CREATE TABLE $quoted_tablename (" . trim($ddl_fields_defs, " \n\r\t,") . ')';
        $return_queries[] = "INSERT INTO $quoted_tablename SELECT * FROM " . $this->getDb()->quoteTableName($tmp_table_name);
        $return_queries[] = 'DROP TABLE ' . $this->getDb()->quoteTableName($tmp_table_name);

        $return_queries = array_merge($return_queries, $this->getIndexSqls($unquoted_tablename));

        $return_queries[] = "RELEASE add_primary_key_to_$tmp_table_name";
        $return_queries[] = "PRAGMA foreign_keys = $foreign_keys_state";

        return implode(';', $return_queries);
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
     * @param array|string $columns the name of the column to that the constraint will be added on. If there are
     * multiple columns, separate them with commas. The name will be properly quoted by the method.
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return string the SQL statement for adding an unique constraint to an existing table.
     */
    public function addUnique(string $name, string $table, $columns): string
    {
        return $this->createIndex($name, $table, $columns, true);
    }

    /**
     * Creates a SQL command for dropping an unique constraint.
     *
     * @param string $name the name of the unique constraint to be dropped. The name will be properly quoted by the
     * method.
     * @param string $table the table whose unique constraint is to be dropped. The name will be properly quoted by the
     * method.
     *
     * @return string the SQL statement for dropping an unique constraint.
     */
    public function dropUnique(string $name, string $table): string
    {
        return "DROP INDEX $name";
    }

    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     *
     * @param string $name the name of the check constraint. The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to. The name will be properly quoted by
     * the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     *
     * @throws Exception|NotSupportedException
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
     * @throws Exception|NotSupportedException
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
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
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     *
     * @return array the generated SQL statement (the first array element) and the corresponding parameters to be bound
     * to the SQL statement (the second array element). The parameters returned include those provided in `$params`.
     * @psalm-return array{string, array<array-key, mixed>}
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

    /**
     * Builds a SQL statement for creating a new index.
     *
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by
     * the method.
     * @param array|string $columns the column(s) that should be included in the index. If there are multiple columns,
     * separate them with commas or use an array to represent them. Each column name will be properly quoted by the
     * method, unless a parenthesis is found in the name.
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     *
     * @throws Exception|InvalidArgumentException
     *
     * @return string the SQL statement for creating a new index.
     */
    public function createIndex(string $name, string $table, $columns, bool $unique = false): string
    {
        $tableParts = explode('.', $table);

        $schema = null;
        if (count($tableParts) === 2) {
            [$schema, $table] = $tableParts;
        }

        return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
            . $this->getDb()->quoteTableName(($schema ? $schema . '.' : '') . $name) . ' ON '
            . $this->getDb()->quoteTableName($table)
            . ' (' . $this->buildColumns($columns) . ')';
    }

    /**
     * @param array $unions
     * @param array $params the binding parameters to be populated.
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
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

        return trim($result);
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
     * @throws Exception|InvalidConfigException|JsonException|NotSupportedException if this is not supported by the
     * underlying DBMS.
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

        $insertSql = 'INSERT OR IGNORE INTO ' . $this->getDb()->quoteTableName($table)
            . (!empty($insertNames) ? ' (' . implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : $values);

        if ($updateColumns === false) {
            return $insertSql;
        }

        $updateCondition = ['or'];
        $quotedTableName = $this->getDb()->quoteTableName($table);

        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            foreach ($constraint->getColumnNames() as $name) {
                $quotedName = $this->getDb()->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=(SELECT $quotedName FROM `EXCLUDED`)";
            }
            $updateCondition[] = $constraintCondition;
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            foreach ($updateNames as $name) {
                $quotedName = $this->getDb()->quoteColumnName($name);

                if (strrpos($quotedName, '.') === false) {
                    $quotedName = "(SELECT $quotedName FROM `EXCLUDED`)";
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }

        $updateSql = 'WITH "EXCLUDED" (' . implode(', ', $insertNames)
            . ') AS (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')'
            : ltrim($values, ' ')) . ') ' . $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }

    private function unquoteTableName(string $tableName): string
    {
        return $this->getDb()->getSchema()->unquoteSimpleTableName($this->getDb()->quoteSql($tableName));
    }

    private function getFieldDefinitionsTokens(string $tableName): ?SqlToken
    {
        $create_table = $this->getCreateTable($tableName);

        /** Parse de CREATE TABLE statement to skip any use of this column, namely field definitions and FOREIGN KEYS */
        $code = (new SqlTokenizer($create_table))->tokenize();
        $pattern = (new SqlTokenizer('any CREATE any TABLE any()'))->tokenize();
        if (!$code[0]->matches($pattern, 0, $firstMatchIndex, $lastMatchIndex)) {
            throw new InvalidParamException("Table not found: $tableName");
        }

        /** Get the fields definition and foreign keys tokens */
        return $code[0][$lastMatchIndex - 1];
    }

    private function getCreateTable(string $tableName): string
    {
        if (($pos = strpos($tableName, '.')) !== false) {
            $schema = substr($tableName, 0, $pos + 1);
            $tableName = substr($tableName, $pos + 1);
        } else {
            $schema = '';
        }

        $create_table = $this->getDb()->createCommand(
            "select SQL from {$schema}SQLite_Master where tbl_name = '$tableName' and type='table'"
        )->queryScalar();

        if ($create_table === null) {
            throw new InvalidParamException("Table not found: $tableName");
        }

        return trim($create_table);
    }

    /**
     * @return false|string|null
     */
    private function foreignKeysState()
    {
        return $this->getDb()->createCommand('PRAGMA foreign_keys')->queryScalar();
    }

    private function getIndexSqls(string $tableName, $skipColumn = null, $newColumn = null): array
    {
        /** Get all indexes on this table */
        $indexes = $this->getDb()->createCommand(
            "select SQL from SQLite_Master where tbl_name = '$tableName' and type='index'"
        )->queryAll();

        if ($skipColumn === null) {
            return array_column($indexes, 'sql');
        }

        $quoted_skip_column = $this->getDb()->quoteColumnName((string) $skipColumn);
        if ($newColumn === null) {
            /** Skip indexes which contain this column */
            foreach ($indexes as $key => $index) {
                $code = (new SqlTokenizer($index['sql']))->tokenize();
                $pattern = (new SqlTokenizer('any CREATE any INDEX any ON any()'))->tokenize();

                /** Extract the list of fields of this index */
                if (!$code[0]->matches($pattern, 0, $firstMatchIndex, $lastMatchIndex)) {
                    throw new InvalidParamException("Index definition error: $index");
                }

                $found = false;
                $indexFieldsDef = $code[0][$lastMatchIndex - 1];
                $offset = 0;
                while ($indexFieldsDef->offsetExists($offset)) {
                    $token = $indexFieldsDef[$offset];
                    $tokenType = $token->getType();
                    if ($tokenType === SqlToken::TYPE_IDENTIFIER) {
                        if ((string) $token === $skipColumn || (string) $token === $quoted_skip_column) {
                            $found = true;
                            unset($indexes[$key]);
                            break;
                        }
                    }
                    ++$offset;
                }

                if (!$found) {
                    /** If the index contains this column, do not add it */
                    $indexes[$key] = $index['sql'];
                }
            }
        } else {
            foreach ($indexes as $key => $index) {
                $code = (new SqlTokenizer($index['sql']))->tokenize();
                $pattern = (new SqlTokenizer('any CREATE any INDEX any ON any ()'))->tokenize();

                /** Extract the list of fields of this index */
                if (!$code[0]->matches($pattern, 0, $firstMatchIndex, $lastMatchIndex)) {
                    throw new InvalidParamException("Index definition error: $index");
                }

                $indexFieldsDef = $code[0][$lastMatchIndex - 1];
                $new_index_def = '';

                for ($i = 0; $i < $lastMatchIndex - 1; ++$i) {
                    $new_index_def .= $code[0][$i] . ' ';
                }

                $offset = 0;
                while ($indexFieldsDef->offsetExists($offset)) {
                    $token = $indexFieldsDef[$offset];
                    $tokenType = $token->getType();
                    if ($tokenType === SqlToken::TYPE_IDENTIFIER) {
                        if ((string) $token === $skipColumn || (string) $token === $quoted_skip_column) {
                            $token = $this->getDb()->quoteColumnName((string) $newColumn);
                        }
                    }
                    $new_index_def .= $token;
                    ++$offset;
                }

                while ($code[0]->offsetExists($lastMatchIndex)) {
                    $new_index_def .= $code[0][$lastMatchIndex++] . ' ';
                }

                $indexes[$key] = $this->dropIndex((string) $code[0][2], $tableName) . ";$new_index_def";
            }
        }

        return $indexes;
    }
}
