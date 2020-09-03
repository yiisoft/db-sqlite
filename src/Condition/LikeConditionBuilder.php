<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Condition;

use Yiisoft\Db\Query\Conditions\LikeConditionBuilder as BaseLikeConditionBuilder;

final class LikeConditionBuilder extends BaseLikeConditionBuilder
{
    protected ?string $escapeCharacter = '\\';
}
