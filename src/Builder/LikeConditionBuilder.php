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
        parent::__construct($queryBuilder);
    }
}
