<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\QueryBuilder\Condition\Builder\LikeConditionBuilder as BaseLikeConditionBuilder;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;

final class LikeConditionBuilder extends BaseLikeConditionBuilder
{
    private string $escapeCharacter = '\\';

    public function __construct(QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder, $this->getEscapeSql());
    }

    /**
     * @return string character used to escape special characters in LIKE conditions.
     *
     * By default, it's assumed to be `\`.
     */
    private function getEscapeSql(): string
    {
        return $this->escapeCharacter !== '' ? " ESCAPE '{$this->escapeCharacter}'" : '';
    }
}
