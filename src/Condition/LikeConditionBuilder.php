<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Condition;

use Yiisoft\Db\Query\Conditions\LikeConditionBuilder as BaseLikeConditionBuilder;
use Yiisoft\Db\Query\QueryBuilderInterface;

final class LikeConditionBuilder extends BaseLikeConditionBuilder
{
    protected ?string $escapeCharacter = '\\';

    public function __construct(private QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder);
    }
}
