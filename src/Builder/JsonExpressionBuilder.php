<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use JsonException;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionBuilderInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Json\Json;

/**
 * Builds expressions for {@see `Yiisoft\Db\Expression\JsonExpression`} for SQLite.
 */
final class JsonExpressionBuilder implements ExpressionBuilderInterface
{
    public function __construct(private QueryBuilderInterface $queryBuilder)
    {
    }

    /**
     * The method builds the raw SQL from the `$expression` that won't be additionally escaped or quoted.
     *
     * @param JsonExpression $expression The expression to build.
     * @param array $params The binding parameters.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws JsonException
     * @throws NotSupportedException
     *
     * @return string The raw SQL that won't be additionally escaped or quoted.
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $value = $expression->getValue();

        if ($value instanceof QueryInterface) {
            [$sql, $params] = $this->queryBuilder->build($value, $params);

            return "($sql)";
        }

        return $this->queryBuilder->bindParam(Json::encode($value), $params);
    }
}
