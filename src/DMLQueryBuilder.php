<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use InvalidArgumentException;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDMLQueryBuilder;

use function array_map;
use function implode;

/**
 * Implements a DML (Data Manipulation Language) SQL statements for SQLite Server.
 */
final class DMLQueryBuilder extends AbstractDMLQueryBuilder
{
    public function insertReturningPks(string $table, array|QueryInterface $columns, array &$params = []): string
    {
        $insertSql = $this->insert($table, $columns, $params);
        $tableSchema = $this->schema->getTableSchema($table);
        $primaryKeys = $tableSchema?->getPrimaryKey() ?? [];

        if (empty($primaryKeys)) {
            return $insertSql;
        }

        $primaryKeys = array_map($this->quoter->quoteColumnName(...), $primaryKeys);

        return $insertSql . ' RETURNING ' . implode(', ', $primaryKeys);
    }

    public function resetSequence(string $table, int|string|null $value = null): string
    {
        $tableSchema = $this->schema->getTableSchema($table);

        if ($tableSchema === null) {
            throw new InvalidArgumentException("Table not found: '$table'.");
        }

        $sequenceName = $tableSchema->getSequenceName();

        if ($sequenceName === null) {
            throw new InvalidArgumentException("There is not sequence associated with table '$table'.'");
        }

        $tableName = $this->quoter->quoteTableName($table);

        if ($value !== null) {
            $value = "'" . ((int) $value - 1) . "'";
        } else {
            $key = $tableSchema->getPrimaryKey()[0];
            $key = $this->quoter->quoteColumnName($key);
            $value = '(SELECT MAX(' . $key . ') FROM ' . $tableName . ')';
        }

        return 'UPDATE sqlite_sequence SET seq=' . $value . " WHERE name='" . $tableSchema->getName() . "'";
    }

    public function upsert(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns = true,
        array &$params = [],
    ): string {
        $insertSql = $this->insert($table, $insertColumns, $params);

        [$uniqueNames, , $updateNames] = $this->prepareUpsertColumns($table, $insertColumns, $updateColumns);

        if (empty($uniqueNames)) {
            return $insertSql;
        }

        if (empty($updateColumns) || $updateNames === []) {
            /** there are no columns to update */
            return "$insertSql ON CONFLICT DO NOTHING";
        }

        $quotedUniqueNames = array_map($this->quoter->quoteColumnName(...), $uniqueNames);
        /** @psalm-suppress PossiblyInvalidArgument */
        $updates = $this->prepareUpsertSets($table, $updateColumns, $updateNames, $params);

        return $insertSql
            . ' ON CONFLICT (' . implode(', ', $quotedUniqueNames) . ')'
            . ' DO UPDATE SET ' . implode(', ', $updates);
    }

    public function upsertReturning(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns = true,
        ?array $returnColumns = null,
        array &$params = [],
    ): string {
        $upsertSql = $this->upsert($table, $insertColumns, $updateColumns, $params);

        $returnColumns ??= $this->schema->getTableSchema($table)?->getColumnNames();

        if (empty($returnColumns)) {
            return $upsertSql;
        }

        $returnColumns = array_map($this->quoter->quoteColumnName(...), $returnColumns);

        if (str_ends_with($upsertSql, ' ON CONFLICT DO NOTHING')) {
            $dummyColumn = $this->getDummyColumn($table);

            $upsertSql = substr($upsertSql, 0, -10) . "DO UPDATE SET $dummyColumn = $dummyColumn";
        }

        return $upsertSql . ' RETURNING ' . implode(', ', $returnColumns);
    }

    private function getDummyColumn(string $table): string
    {
        /** @psalm-suppress PossiblyNullReference */
        $columns = $this->schema->getTableSchema($table)->getColumns();

        foreach ($columns as $column) {
            if ($column->isPrimaryKey() || $column->isUnique()) {
                continue;
            }

            /** @psalm-suppress PossiblyNullArgument */
            return $this->quoter->quoteColumnName($column->getName());
        }

        /** @psalm-suppress PossiblyNullArgument, PossiblyFalseReference */
        return $this->quoter->quoteColumnName(end($columns)->getName());
    }
}
