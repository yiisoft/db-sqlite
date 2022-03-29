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
use Yiisoft\Db\Query\DMLQueryBuilder as AbstractDMLQueryBuilder;
use Yiisoft\Db\Query\QueryBuilderInterface;
use Yiisoft\Db\Query\QueryInterface;

final class DMLQueryBuilder extends AbstractDMLQueryBuilder
{
    public function __construct(private QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder);
    }

    /**
     * @throws Exception|Throwable
     */
    public function resetSequence(string $tableName, mixed $value = null): string
    {
        $table = $this->queryBuilder->schema()->getTableSchema($tableName);

        if ($table !== null && $table->getSequenceName() !== null) {
            $tableName = $this->queryBuilder->quoter()->quoteTableName($tableName);
            if ($value === null) {
                $pk = $table->getPrimaryKey();
                $key = $this->queryBuilder->quoter()->quoteColumnName(reset($pk));
                $value = $this->queryBuilder->command()->setSql("SELECT MAX($key) FROM $tableName")->queryScalar();
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
     * @throws Exception|InvalidArgumentException|InvalidConfigException|JsonException|NotSupportedException
     */
    public function upsert(
        string $table,
        QueryInterface|array $insertColumns,
        bool|array $updateColumns,
        array &$params
    ): string {
        /** @var Constraint[] $constraints */
        $constraints = [];

        /**
         * @psalm-var string[] $insertNames
         * @psalm-var string[] $updateNames
         * @psalm-var array<string, ExpressionInterface|string>|bool $updateColumns
         */
        [$uniqueNames, $insertNames, $updateNames] = $this->queryBuilder->prepareUpsertColumns(
            $table,
            $insertColumns,
            $updateColumns,
            $constraints
        );

        if (empty($uniqueNames)) {
            return $this->insert($table, $insertColumns, $params);
        }

        /**
         * @psalm-var string[] $placeholders
         */
        [, $placeholders, $values, $params] = $this->queryBuilder->prepareInsertValues($table, $insertColumns, $params);

        $insertSql = 'INSERT OR IGNORE INTO '
            . $this->queryBuilder->quoter()->quoteTableName($table)
            . (!empty($insertNames) ? ' (' . implode(', ', $insertNames) . ')' : '')
            . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : "$values");

        if ($updateColumns === false) {
            return $insertSql;
        }

        $updateCondition = ['or'];
        $quotedTableName = $this->queryBuilder->quoter()->quoteTableName($table);

        foreach ($constraints as $constraint) {
            $constraintCondition = ['and'];
            /** @psalm-var string[] */
            $columnsNames = $constraint->getColumnNames();
            foreach ($columnsNames as $name) {
                $quotedName = $this->queryBuilder->quoter()->quoteColumnName($name);
                $constraintCondition[] = "$quotedTableName.$quotedName=(SELECT $quotedName FROM `EXCLUDED`)";
            }
            $updateCondition[] = $constraintCondition;
        }

        if ($updateColumns === true) {
            $updateColumns = [];
            foreach ($updateNames as $name) {
                $quotedName = $this->queryBuilder->quoter()->quoteColumnName($name);

                if (strrpos($quotedName, '.') === false) {
                    $quotedName = "(SELECT $quotedName FROM `EXCLUDED`)";
                }
                $updateColumns[$name] = new Expression($quotedName);
            }
        }

        /** @var array $params */
        $updateSql = 'WITH "EXCLUDED" ('
            . implode(', ', $insertNames)
            . ') AS (' . (!empty($placeholders)
                ? 'VALUES (' . implode(', ', $placeholders) . ')'
                : ltrim("$values", ' ')) . ') ' .
                $this->update($table, $updateColumns, $updateCondition, $params);

        return "$updateSql; $insertSql;";
    }
}
