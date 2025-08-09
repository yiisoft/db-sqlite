<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;

/**
 * Build an object of {@see \Yiisoft\Db\QueryBuilder\Condition\LikeCondition} into SQL expressions for SQLite Server.
 */
final class LikeBuilder extends \Yiisoft\Db\QueryBuilder\Condition\Builder\LikeBuilder
{
    protected const ESCAPE_SQL = " ESCAPE '\\'";

    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        if ($expression->caseSensitive === true) {
            throw new NotSupportedException('SQLite doesn\'t support case-sensitive "LIKE" conditions.');
        }
        return parent::build($expression, $params);
    }
}
