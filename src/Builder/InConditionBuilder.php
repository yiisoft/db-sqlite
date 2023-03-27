<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Iterator;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;

use function implode;
use function is_array;
use function str_contains;

/**
 * Build an object of {@see \Yiisoft\Db\QueryBuilder\Condition\InCondition} into SQL expressions for SQLite Server.
 */
final class InConditionBuilder extends \Yiisoft\Db\QueryBuilder\Condition\Builder\InConditionBuilder
{
    /**
     * Builds SQL for IN condition.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    protected function buildSubqueryInCondition(
        string $operator,
        iterable|string|Iterator $columns,
        ExpressionInterface $values,
        array &$params = []
    ): string {
        if (is_array($columns)) {
            throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
        }

        return parent::buildSubqueryInCondition($operator, $columns, $values, $params);
    }

    /**
     * Builds SQL for IN condition.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    protected function buildCompositeInCondition(
        string|null $operator,
        iterable $columns,
        iterable|Iterator $values,
        array &$params = []
    ): string {
        $quotedColumns = [];

        /** @psalm-var string[] $columns */
        foreach ($columns as $i => $column) {
            if ($column instanceof ExpressionInterface) {
                $quotedColumns[$i] = $this->queryBuilder->buildExpression($column);
                continue;
            }
            $quotedColumns[$i] = !str_contains($column, '(')
                ? $this->queryBuilder->quoter()->quoteColumnName($column) : $column;
        }

        $vss = [];

        /** @psalm-var string[][] $values */
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $i => $column) {
                if ($column instanceof ExpressionInterface) {
                    $column = $this->queryBuilder->buildExpression($column);
                }
                if (isset($value[$column])) {
                    $phName = $this->queryBuilder->bindParam($value[$column], $params);
                    $vs[] = $quotedColumns[$i] . ($operator === 'IN' ? ' = ' : ' != ') . $phName;
                } else {
                    $vs[] = $quotedColumns[$i] . ($operator === 'IN' ? ' IS' : ' IS NOT') . ' NULL';
                }
            }
            $vss[] = '(' . implode($operator === 'IN' ? ' AND ' : ' OR ', $vs) . ')';
        }

        return '(' . implode($operator === 'IN' ? ' OR ' : ' AND ', $vss) . ')';
    }
}
