<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\QueryBuilder\Conditions\Builder\LikeConditionBuilder as BaseLikeConditionBuilder;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;

final class LikeConditionBuilder extends BaseLikeConditionBuilder
{
    protected string|null $escapeCharacter = '\\';

    public function __construct(QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder, $this->getEscapeSql());
    }

    /**
     * @return string character used to escape special characters in LIKE conditions. By default,
     * it's assumed to be `\`.
     */
    private function getEscapeSql(): string
    {
        if ($this->escapeCharacter !== null) {
            return " ESCAPE '{$this->escapeCharacter}'";
        }

        return '';
    }
}
