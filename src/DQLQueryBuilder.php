<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Expression\ExpressionBuilderInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\AbstractDQLQueryBuilder;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\QueryBuilder\Condition\LikeCondition;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Builder\InConditionBuilder;
use Yiisoft\Db\Sqlite\Builder\LikeConditionBuilder;

use function array_filter;
use function array_merge;
use function implode;
use function trim;

/**
 * Implements a DQL (Data Query Language) SQL statements for SQLite Server.
 */
final class DQLQueryBuilder extends AbstractDQLQueryBuilder
{
    public function __construct(
        private QueryBuilderInterface $queryBuilder,
        QuoterInterface $quoter,
        SchemaInterface $schema
    ) {
        parent::__construct($queryBuilder, $quoter, $schema);
    }

    public function build(QueryInterface $query, array $params = []): array
    {
        $query = $query->prepare($this->queryBuilder);

        $params = empty($params) ? $query->getParams() : array_merge($params, $query->getParams());

        $clauses = [
            $this->buildSelect($query->getSelect(), $params, $query->getDistinct(), $query->getSelectOption()),
            $this->buildFrom($query->getFrom(), $params),
            $this->buildJoin($query->getJoin(), $params),
            $this->buildWhere($query->getWhere(), $params),
            $this->buildGroupBy($query->getGroupBy()),
            $this->buildHaving($query->getHaving(), $params),
        ];

        $orderBy = $query->getOrderBy();
        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->buildOrderByAndLimit($sql, $orderBy, $query->getLimit(), $query->getOffset());

        if (!empty($orderBy)) {
            /** @psalm-var array<string|ExpressionInterface> $orderBy */
            foreach ($orderBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $groupBy = $query->getGroupBy();

        if (!empty($groupBy)) {
            /** @psalm-var array<string|ExpressionInterface> $groupBy */
            foreach ($groupBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $union = $this->buildUnion($query->getUnion(), $params);

        if ($union !== '') {
            $sql = "$sql$this->separator$union";
        }

        $with = $this->buildWithQueries($query->getWithQueries(), $params);

        if ($with !== '') {
            $sql = "$with$this->separator$sql";
        }

        return [$sql, $params];
    }

    public function buildLimit(ExpressionInterface|int|null $limit, ExpressionInterface|int|null $offset): string
    {
        $sql = '';

        if ($this->hasLimit($limit)) {
            $sql = 'LIMIT ' . ($limit instanceof ExpressionInterface ? $this->buildExpression($limit) : (string)$limit);
            if ($this->hasOffset($offset)) {
                $sql .= ' OFFSET ' .
                    ($offset instanceof ExpressionInterface ? $this->buildExpression($offset) : (string)$offset);
            }
        } elseif ($this->hasOffset($offset)) {
            /**
             * Limit isn't optional in SQLite.
             *
             * {@see http://www.sqlite.org/syntaxdiagrams.html#select-stmt}
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
        foreach ($unions as $i => $union) {
            $query = $union['query'];
            if ($query instanceof QueryInterface) {
                [$unions[$i]['query'], $params] = $this->build($query, $params);
            }

            $result .= ' UNION ' . ($union['all'] ? 'ALL ' : '') . ' ' . (string) $unions[$i]['query'];
        }

        return trim($result);
    }

    /**
     * Has an array of default expression builders.
     *
     * Extend this method and override it if you want to change default expression builders for this query builder.
     *
     * {@see ExpressionBuilder} docs for details.
     *
     * @psalm-return array<string, class-string<ExpressionBuilderInterface>>
     */
    protected function defaultExpressionBuilders(): array
    {
        return array_merge(parent::defaultExpressionBuilders(), [
            LikeCondition::class => LikeConditionBuilder::class,
            InCondition::class => InConditionBuilder::class,
        ]);
    }
}
