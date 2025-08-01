<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;

/**
 * Build an object of {@see \Yiisoft\Db\QueryBuilder\Condition\LikeCondition} into SQL expressions for SQLite Server.
 */
final class LikeBuilder extends \Yiisoft\Db\QueryBuilder\Condition\Builder\LikeBuilder
{
    private string $escapeCharacter = '\\';

    public function __construct(QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder, $this->getEscapeSql());
    }

    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        if ($expression->caseSensitive === true) {
            throw new NotSupportedException('SQLite doesn\'t support case-sensitive "LIKE" conditions.');
        }
        return parent::build($expression, $params);
    }

    /**
     * @return string Character used to escape special characters in `LIKE` conditions.
     * By default, it's assumed to be `\`.
     */
    private function getEscapeSql(): string
    {
        return $this->escapeCharacter !== '' ? " ESCAPE '$this->escapeCharacter'" : '';
    }
}
