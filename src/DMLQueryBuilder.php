<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDMLQueryBuilder;

use function implode;

/**
 * Implements a DML (Data Manipulation Language) SQL statements for SQLite Server.
 */
final class DMLQueryBuilder extends AbstractDMLQueryBuilder
{
    public function insertWithReturningPks(string $table, QueryInterface|array $columns, array &$params = []): string
    {
        throw new NotSupportedException(__METHOD__ . '() is not supported by SQLite.');
    }

    public function resetSequence(string $table, int|string $value = null): string
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
        QueryInterface|array $insertColumns,
        bool|array $updateColumns,
        array &$params
    ): string {
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

        $quotedTableName = $this->quoter->quoteTableName($table);

        $insertSql = 'INSERT OR IGNORE INTO ' . $quotedTableName
            . (!empty($insertNames) ? ' (' . implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : ' ' . $values);

        if ($updateColumns === false) {
            return $insertSql;
        }

        $updateCondition = ['or'];

        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            /** @psalm-var string[] $columnNames */
            $columnNames = $constraint->getColumnNames();
            foreach ($columnNames as $name) {
                $quotedName = $this->quoter->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=(SELECT $quotedName FROM `EXCLUDED`)";
            }
            $updateCondition[] = $constraintCondition;
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            /** @psalm-var string[] $updateNames */
            foreach ($updateNames as $quotedName) {
                $updateColumns[$quotedName] = new Expression("(SELECT $quotedName FROM `EXCLUDED`)");
            }
        }

        if ($updateColumns === []) {
            return $insertSql;
        }

        $updateSql = 'WITH "EXCLUDED" (' . implode(', ', $insertNames) . ') AS ('
            . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : $values)
            . ') ' . $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }
}
