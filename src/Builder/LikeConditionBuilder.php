<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\Condition\Interface\LikeConditionInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;

/**
 * Build an object of {@see \Yiisoft\Db\QueryBuilder\Condition\LikeCondition} into SQL expressions for SQLite Server.
 */
final class LikeConditionBuilder extends \Yiisoft\Db\QueryBuilder\Condition\Builder\LikeConditionBuilder
{
    private string $escapeCharacter = '\\';

    public function __construct(QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder, $this->getEscapeSql());
    }

    public function build(LikeConditionInterface $expression, array &$params = []): string
    {
        if ($expression->getCaseSensitive() === true) {
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
