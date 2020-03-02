<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Condition;

use Yiisoft\Db\Exceptions\NotSupportedException;
use Yiisoft\Db\Querys\Query;
use Yiisoft\Db\Querys\Conditions\InConditionBuilder as BaseInConditionBuilder;

/**
 * {@inheritdoc}
 */
class InConditionBuilder extends BaseInConditionBuilder
{
    /**
     * {@inheritdoc}
     *
     * @throws NotSupportedException if `$columns` is an array
     */
    protected function buildSubqueryInCondition(string $operator, $columns, Query $values, array &$params): string
    {
        if (\is_array($columns)) {
            throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
        }

        return parent::buildSubqueryInCondition($operator, $columns, $values, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCompositeInCondition(?string $operator, $columns, $values, &$params): string
    {
        $quotedColumns = [];

        foreach ($columns as $i => $column) {
            $quotedColumns[$i] = strpos($column, '(') === false ? $this->queryBuilder->db->quoteColumnName($column) : $column;
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
