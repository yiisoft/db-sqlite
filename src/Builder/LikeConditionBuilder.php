<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Query\Conditions\Builder\LikeConditionBuilder as BaseLikeConditionBuilder;
use Yiisoft\Db\Query\QueryBuilderInterface;

final class LikeConditionBuilder extends BaseLikeConditionBuilder
{
    protected ?string $escapeCharacter = '\\';

    public function __construct(QueryBuilderInterface $queryBuilder)
    {
        parent::__construct($queryBuilder);
    }
}
