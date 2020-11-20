<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Condition;

use function implode;
use function is_array;
use function strpos;
use Traversable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;

use Yiisoft\Db\Query\Conditions\InConditionBuilder as BaseInConditionBuilder;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection;

final class InConditionBuilder extends BaseInConditionBuilder
{
    /**
     * Builds SQL for IN condition.
     *
     * @param string $operator
     * @param array|string $columns
     * @param Query $values
     * @param array $params
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     *
     * @return string SQL.
     */
    protected function buildSubqueryInCondition(string $operator, $columns, Query $values, array &$params = []): string
    {
        if (is_array($columns)) {
            throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
        }

        return parent::buildSubqueryInCondition($operator, $columns, $values, $params);
    }

    /**
     * Builds SQL for IN condition.
     *
     * @param string|null $operator
     * @param array|Traversable $columns
     * @param array|iterable $values
     * @param array $params
     *
     * @return string SQL.
     */
    protected function buildCompositeInCondition(?string $operator, $columns, $values, array &$params = []): string
    {
        /** @var Connection $db */
        $db = $this->queryBuilder->getDb();

        $quotedColumns = [];

        foreach ($columns as $i => $column) {
            $quotedColumns[$i] = strpos($column, '(') === false
                ? $db->quoteColumnName($column) : $column;
        }

        $vss = [];

        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $i => $column) {
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
