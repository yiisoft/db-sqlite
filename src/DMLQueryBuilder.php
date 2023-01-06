<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use JsonException;
use Throwable;
use Yiisoft\Db\Constraint\Constraint;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDMLQueryBuilder;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;

use function implode;
use function ltrim;
use function reset;

final class DMLQueryBuilder extends AbstractDMLQueryBuilder
{
    public function __construct(
        QueryBuilderInterface $queryBuilder,
        private QuoterInterface $quoter,
        private SchemaInterface $schema
    ) {
        parent::__construct($queryBuilder, $quoter, $schema);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function insertWithReturningPks(string $table, QueryInterface|array $columns, array &$params = []): string
    {
        throw new NotSupportedException(__METHOD__ . '() is not supported by this DBMS.');
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function resetSequence(string $tableName, int|string $value = null): string
    {
        $table = $this->schema->getTableSchema($tableName);

        if ($table === null) {
            throw new InvalidArgumentException("Table not found: '$tableName'.");
        }

        $sequenceName = $table->getSequenceName();

        if ($sequenceName === null) {
            throw new InvalidArgumentException("There is not sequence associated with table '$tableName'.'");
        }

        $tableName = $this->quoter->quoteTableName($tableName);

        if ($value !== null) {
            $value = "'" . ((int) $value - 1) . "'";
        } else {
            $pk = $table->getPrimaryKey();
            $key = $this->quoter->quoteColumnName(reset($pk));
            $value = '(SELECT MAX(' . $key . ') FROM ' . $tableName . ')';
        }

        return 'UPDATE sqlite_sequence SET seq=' . $value . " WHERE name='" . $table->getName() . "'";
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws JsonException
     * @throws NotSupportedException
     */
    public function upsert(
        string $table,
        QueryInterface|array $insertColumns,
        bool|array $updateColumns,
        array &$params
    ): string {
        /** @psalm-var Constraint[] $constraints */
        $constraints = [];

        /**
         * @psalm-var string[] $insertNames
         * @psalm-var string[] $updateNames
         * @psalm-var array<string, ExpressionInterface|string>|bool $updateColumns
         */
        [$uniqueNames, $insertNames, $updateNames] = $this->prepareUpsertColumns(
            $table,
            $insertColumns,
            $updateColumns,
            $constraints
        );

        if (empty($uniqueNames)) {
            return $this->insert($table, $insertColumns, $params);
        }

        /** @psalm-var string[] $placeholders */
        [, $placeholders, $values, $params] = $this->prepareInsertValues($table, $insertColumns, $params);

        $insertSql = 'INSERT OR IGNORE INTO '
            . $this->quoter->quoteTableName($table)
            . (!empty($insertNames) ? ' (' . implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : "$values");

        if ($updateColumns === false) {
            return $insertSql;
        }

        $updateCondition = ['or'];
        $quotedTableName = $this->quoter->quoteTableName($table);

        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            /** @psalm-var string[] $columnsNames */
            $columnsNames = $constraint->getColumnNames();
            foreach ($columnsNames as $name) {
                $quotedName = $this->quoter->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=(SELECT $quotedName FROM `EXCLUDED`)";
            }
            $updateCondition[] = $constraintCondition;
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            foreach ($updateNames as $name) {
                $quotedName = $this->quoter->quoteColumnName($name);

                if (strrpos($quotedName, '.') === false) {
                    $quotedName = "(SELECT $quotedName FROM `EXCLUDED`)";
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }

        if ($updateColumns === []) {
            return $insertSql;
        }

        /** @psalm-var array $params */
        $updateSql = 'WITH "EXCLUDED" ('
            . implode(', ', $insertNames)
            . ') AS (' . (!empty($placeholders)
                ? 'VALUES (' . implode(', ', $placeholders) . ')'
                : ltrim("$values", ' ')) . ') ' .
                $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }
}
