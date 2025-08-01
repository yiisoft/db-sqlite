<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDQLQueryBuilder;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\QueryBuilder\Condition\Like;
use Yiisoft\Db\QueryBuilder\Condition\JsonOverlaps;
use Yiisoft\Db\Sqlite\Builder\InBuilder;
use Yiisoft\Db\Sqlite\Builder\JsonOverlapsBuilder;
use Yiisoft\Db\Sqlite\Builder\LikeBuilder;

use function array_filter;
use function array_merge;
use function implode;
use function trim;

/**
 * Implements a DQL (Data Query Language) SQL statements for SQLite Server.
 */
final class DQLQueryBuilder extends AbstractDQLQueryBuilder
{
    public function build(QueryInterface $query, array $params = []): array
    {
        $query = $query->prepare($this->queryBuilder);

        $params = empty($params) ? $query->getParams() : array_merge($params, $query->getParams());

        $clauses = [
            $this->buildSelect($query->getSelect(), $params, $query->getDistinct(), $query->getSelectOption()),
            $this->buildFrom($query->getFrom(), $params),
            $this->buildJoin($query->getJoins(), $params),
            $this->buildWhere($query->getWhere(), $params),
            $this->buildGroupBy($query->getGroupBy()),
            $this->buildHaving($query->getHaving(), $params),
            $this->buildFor($query->getFor()),
        ];

        $orderBy = $query->getOrderBy();
        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->buildOrderByAndLimit($sql, $orderBy, $query->getLimit(), $query->getOffset());

        if (!empty($orderBy)) {
            foreach ($orderBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $groupBy = $query->getGroupBy();

        if (!empty($groupBy)) {
            foreach ($groupBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $union = $this->buildUnion($query->getUnions(), $params);

        if ($union !== '') {
            $sql = "$sql$this->separator$union";
        }

        $with = $this->buildWithQueries($query->getWithQueries(), $params);

        if ($with !== '') {
            $sql = "$with$this->separator$sql";
        }

        return [$sql, $params];
    }

    public function buildFor(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        throw new NotSupportedException('SQLite don\'t supports FOR clause.');
    }

    public function buildLimit(ExpressionInterface|int|null $limit, ExpressionInterface|int|null $offset): string
    {
        $sql = '';

        if ($limit !== null) {
            $sql = 'LIMIT ' . ($limit instanceof ExpressionInterface ? $this->buildExpression($limit) : (string)$limit);
            if (!empty($offset)) {
                $sql .= ' OFFSET ' .
                    ($offset instanceof ExpressionInterface ? $this->buildExpression($offset) : (string)$offset);
            }
        } elseif (!empty($offset)) {
            /**
             * Limit isn't optional in SQLite.
             *
             * {@see https://www.sqlite.org/syntaxdiagrams.html#select-stmt}
             */
            $sql = 'LIMIT 9223372036854775807 OFFSET ' . // 2^63-1
                ($offset instanceof ExpressionInterface ? $this->buildExpression($offset) : (string)$offset);
        }

        return $sql;
    }

    public function buildUnion(array $unions, array &$params = []): string
    {
        if (empty($unions)) {
            return '';
        }

        $result = '';

        /** @psalm-var array<array-key, array{query: Query|null, all: bool}> $unions */
        foreach ($unions as $union) {
            if ($union['query'] instanceof QueryInterface) {
                [$union['query'], $params] = $this->build($union['query'], $params);
            }

            $result .= ' UNION ' . ($union['all'] ? 'ALL ' : '') . ' ' . (string) $union['query'];
        }

        return trim($result);
    }

    protected function defaultExpressionBuilders(): array
    {
        return [
            ...parent::defaultExpressionBuilders(),
            JsonOverlaps::class => JsonOverlapsBuilder::class,
            Like::class => LikeBuilder::class,
            In::class => InBuilder::class,
        ];
    }
}
