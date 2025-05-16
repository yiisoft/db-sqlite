<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDMLQueryBuilder;

use function array_map;
use function implode;

/**
 * Implements a DML (Data Manipulation Language) SQL statements for SQLite Server.
 */
final class DMLQueryBuilder extends AbstractDMLQueryBuilder
{
    public function insertWithReturningPks(string $table, array|QueryInterface $columns, array &$params = []): string
    {
        throw new NotSupportedException(__METHOD__ . '() is not supported by SQLite.');
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

        if ($updateColumns === false || $updateNames === []) {
            /** there are no columns to update */
            return "$insertSql ON CONFLICT DO NOTHING";
        }

        if ($updateColumns === true) {
            $updateColumns = [];

            /** @psalm-var string[] $updateNames */
            foreach ($updateNames as $name) {
                $updateColumns[$name] = new Expression(
                    'EXCLUDED.' . $this->quoter->quoteColumnName($name)
                );
            }
        }

        [$updates, $params] = $this->prepareUpdateSets($table, $updateColumns, $params);

        return $insertSql
            . ' ON CONFLICT (' . implode(', ', $uniqueNames) . ') DO UPDATE SET ' . implode(', ', $updates);
    }

    public function upsertWithReturningPks(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns = true,
        array &$params = [],
    ): string {
        $sql = $this->upsert($table, $insertColumns, $updateColumns, $params);
        $returnColumns = $this->schema->getTableSchema($table)?->getPrimaryKey();

        if (!empty($returnColumns)) {
            $returnColumns = array_map($this->quoter->quoteColumnName(...), $returnColumns);

            $sql .= ' RETURNING ' . implode(', ', $returnColumns);
        }

        return $sql;
    }
}
