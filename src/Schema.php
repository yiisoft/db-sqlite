<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Constant\ReferentialAction;
use Yiisoft\Db\Constraint\Check;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Constraint\Index;
use Yiisoft\Db\Driver\Pdo\AbstractPdoSchema;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Helper\DbArrayHelper;
use Yiisoft\Db\Schema\Column\ColumnInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Schema\TableSchema;
use Yiisoft\Db\Schema\TableSchemaInterface;

use function array_change_key_case;
use function array_column;
use function array_map;
use function count;
use function in_array;
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
 *     on_update:ReferentialAction::*,
 *     on_delete:ReferentialAction::*
 * }
 * @psalm-type GroupedForeignKeyInfo = array<
 *     string,
 *     list<ForeignKeyInfo>
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
    /**
     * @throws NotSupportedException
     */
    public function getSchemaDefaultValues(string $schema = '', bool $refresh = false): array
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    protected function findConstraints(TableSchemaInterface $table): void
    {
        $tableName = $this->resolveFullName($table->getName(), $table->getSchemaName());

        $table->checks(...$this->getTableMetadata($tableName, SchemaInterface::CHECKS));
        $table->foreignKeys(...$this->getTableMetadata($tableName, SchemaInterface::FOREIGN_KEYS));
        $table->indexes(...$this->getTableMetadata($tableName, SchemaInterface::INDEXES));
    }

    protected function findTableNames(string $schema = ''): array
    {
        /** @var string[] */
        return $this->db->createCommand(
            "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name",
        )->queryColumn();
    }

    protected function loadTableSchema(string $name): ?TableSchemaInterface
    {
        $table = new TableSchema($name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        }

        return null;
    }

    protected function loadTableForeignKeys(string $tableName): array
    {
        $result = [];

        $foreignKeysList = $this->getPragmaForeignKeyList($tableName);
        /** @psalm-var GroupedForeignKeyInfo $foreignKeysList */
        $foreignKeysList = DbArrayHelper::arrange($foreignKeysList, ['table', 'id']);

        foreach ($foreignKeysList as $table => $foreignKeysById) {
            /**
             * @psalm-var GroupedForeignKeyInfo $foreignKeysById
             * @psalm-var int $id
             */
            foreach ($foreignKeysById as $id => $foreignKey) {
                if ($foreignKey[0]['to'] === null) {
                    /** @var Index $primaryKey */
                    $primaryKey = $this->getTablePrimaryKey($table);
                    $foreignColumnNames = $primaryKey->columnNames;
                } else {
                    /** @var string[] $foreignColumnNames */
                    $foreignColumnNames = array_column($foreignKey, 'to');
                }

                $result[] = new ForeignKey(
                    (string) $id,
                    array_column($foreignKey, 'from'),
                    '',
                    $table,
                    $foreignColumnNames,
                    $foreignKey[0]['on_delete'],
                    $foreignKey[0]['on_update'],
                );
            }
        }

        return $result;
    }

    protected function loadTableIndexes(string $tableName): array
    {
        $indexList = $this->db
            ->createCommand('PRAGMA INDEX_LIST(' . $this->db->getQuoter()->quoteValue($tableName) . ')')
            ->queryAll();

        /** @psalm-var IndexListInfo[] $indexes */
        $indexes = array_map(array_change_key_case(...), $indexList);
        $result = [];
        $hasPrimaryKey = false;

        foreach ($indexes as $index) {
            $columns = $this->getPragmaIndexInfo($index['name']);

            $result[$index['name']] = new Index(
                $index['name'],
                array_column($columns, 'name'),
                (bool) $index['unique'],
                $index['origin'] === 'pk',
            );

            $hasPrimaryKey = $hasPrimaryKey || $index['origin'] === 'pk';
        }

        if (!$hasPrimaryKey) {
            /**
             * Extra check for PK in case of `INTEGER PRIMARY KEY` with ROWID.
             *
             * @link https://www.sqlite.org/lang_createtable.html#primkeyconst
             */
            $tableColumns = $this->loadTableColumnsInfo($tableName);

            foreach ($tableColumns as $tableColumn) {
                if ($tableColumn['pk'] > 0) {
                    $result[''] = new Index('', [$tableColumn['name']], true, true);
                    break;
                }
            }
        }

        return $result;
    }

    protected function loadTableChecks(string $tableName): array
    {
        $sql = $this->db->createCommand(
            'SELECT "sql" FROM "sqlite_master" WHERE name = :tableName',
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
                $createTableToken instanceof SqlToken
                && $createTableToken->matches($sqlTokenizerAnyCheck->tokenize(), (int) $offset, $firstMatchIndex, $offset)
            ) {
                $name = '';
                $checkSql = (string) $createTableToken[(int) $offset - 1];
                $pattern = (new SqlTokenizer('CONSTRAINT any'))->tokenize();

                if (
                    isset($createTableToken[(int) $firstMatchIndex - 2])
                    && $createTableToken->matches($pattern, (int) $firstMatchIndex - 2)
                ) {
                    $sqlToken = $createTableToken[(int) $firstMatchIndex - 1];
                    $name = $sqlToken?->getContent() ?? '';
                }

                $result[] = new Check($name, expression: $checkSql);
            }
        }

        return $result;
    }

    protected function loadTableDefaultValues(string $tableName): array
    {
        throw new NotSupportedException('SQLite does not support default value constraints.');
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchemaInterface $table The table metadata.
     *
     * @return bool Whether the table exists in the database.
     */
    protected function findColumns(TableSchemaInterface $table): bool
    {
        $tableName = $table->getName();
        $columns = $this->loadTableColumnsInfo($tableName);
        $jsonColumns = $this->getJsonColumns($table);
        $checks = $this->getTableChecks($tableName);

        foreach ($columns as $info) {
            if (in_array($info['name'], $jsonColumns, true)) {
                $info['type'] = ColumnType::JSON;
            }

            $info['schema'] = $table->getSchemaName();
            $info['table'] = $tableName;

            $column = $this->loadColumn($info, $checks);
            $table->column($info['name'], $column);
        }

        $column = count($table->getPrimaryKey()) === 1 ? $table->getColumn($table->getPrimaryKey()[0]) : null;

        if ($column !== null && !strncasecmp($column->getDbType() ?? '', 'int', 3)) {
            $table->sequenceName('');
            $column->autoIncrement();
        }

        return !empty($columns);
    }

    /**
     * @psalm-param array{
     *     native_type: string,
     *     pdo_type: int,
     *     "sqlite:decl_type"?: string,
     *     table?: string,
     *     flags: string[],
     *     name: string,
     *     len: int,
     *     precision: int,
     * } $metadata
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    protected function loadResultColumn(array $metadata): ?ColumnInterface
    {
        if (empty($metadata['sqlite:decl_type']) && (empty($metadata['native_type']) || $metadata['native_type'] === 'null')) {
            return null;
        }

        $dbType = $metadata['sqlite:decl_type'] ?? $metadata['native_type'];

        $columnInfo = ['fromResult' => true];

        if (!empty($metadata['table'])) {
            $columnInfo['table'] = $metadata['table'];
            $columnInfo['name'] = $metadata['name'];
        } elseif (!empty($metadata['name'])) {
            $columnInfo['name'] = $metadata['name'];
        }

        return $this->db->getColumnFactory()->fromDefinition($dbType, $columnInfo);
    }

    protected function findViewNames(string $schema = ''): array
    {
        /** @var string[] */
        return $this->db->createCommand(
            <<<SQL
            SELECT name FROM sqlite_master WHERE type = 'view' AND name NOT LIKE 'sqlite_%'
            SQL,
        )->queryColumn();
    }

    /**
     * Loads the column information into a {@see ColumnInterface} object.
     *
     * @param array $info The column information.
     * @param Check[] $checks
     *
     * @return ColumnInterface The column object.
     *
     * @psalm-param ColumnInfo $info
     */
    private function loadColumn(array $info, array $checks): ColumnInterface
    {
        return $this->db->getColumnFactory()->fromDefinition($info['type'], [
            'defaultValueRaw' => $info['dflt_value'],
            'name' => $info['name'],
            'notNull' => (bool) $info['notnull'],
            'primaryKey' => (bool) $info['pk'],
            'schema' => $info['schema'],
            'table' => $info['table'],
            'values' => $this->tryGetEnumValuesFromCheck($info['name'], $checks),
        ]);
    }

    /**
     * Returns table columns info.
     *
     * @param string $tableName The table name.
     *
     * @return array The table columns info.
     *
     * @psalm-return ColumnInfo[] $tableColumns;
     */
    private function loadTableColumnsInfo(string $tableName): array
    {
        $tableColumns = $this->db->createCommand(
            'PRAGMA TABLE_INFO(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')',
        )->queryAll();

        /** @psalm-var ColumnInfo[] */
        return array_map(array_change_key_case(...), $tableColumns);
    }

    /**
     * @psalm-return list<ForeignKeyInfo>
     */
    private function getPragmaForeignKeyList(string $tableName): array
    {
        $foreignKeysList = $this->db->createCommand(
            'PRAGMA FOREIGN_KEY_LIST(' . $this->db->getQuoter()->quoteSimpleTableName($tableName) . ')',
        )->queryAll();
        $foreignKeysList = array_map(array_change_key_case(...), $foreignKeysList);
        DbArrayHelper::multisort($foreignKeysList, 'seq');

        /** @psalm-var list<ForeignKeyInfo> $foreignKeysList */
        return $foreignKeysList;
    }

    /**
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

    private function getJsonColumns(TableSchemaInterface $table): array
    {
        $result = [];
        /** @psalm-var Check[] $checks */
        $checks = $this->getTableChecks($table->getFullName());
        $regexp = '/\bjson_valid\(\s*["`\[]?(.+?)["`\]]?\s*\)/i';

        foreach ($checks as $check) {
            if (preg_match_all($regexp, $check->expression, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    $result[] = $match[1];
                }
            }
        }

        return $result;
    }

    /**
     * @param Check[] $checks
     *
     * @psalm-return list<string>|null
     */
    private function tryGetEnumValuesFromCheck(string $name, array $checks): ?array
    {
        if (empty($checks)) {
            return null;
        }

        foreach ($checks as $check) {
            if ($this->isCheckNotStartsFromColumnName($check->expression, $name)) {
                continue;
            }

            preg_match_all(
                "~(?<!\sNOT)\s+IN\s*\(\s*('(?:''|[^'])*')(?:,\s*(?1))*~i",
                $check->expression,
                $block,
            );

            if (empty($block[0][0])) {
                continue;
            }

            preg_match_all("~'((?:''|[^'])*)'~", $block[0][0], $matches);

            return array_map(
                static fn($v) => str_replace("''", "'", $v),
                $matches[1] ?? [],
            );
        }

        return null;
    }

    private function isCheckNotStartsFromColumnName(string $check, string $columnName): bool
    {
        $quotedColumnName = preg_quote($columnName, '~');
        return preg_match("~^\"$quotedColumnName\"\s~", $check) !== 1
            && preg_match("~^(|`|\[)$quotedColumnName(|`|\])\s~i", $check) !== 1;
    }
}
