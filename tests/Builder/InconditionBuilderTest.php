<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Builder;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Conditions\InCondition;
use Yiisoft\Db\Sqlite\Builder\InConditionBuilder;
use Yiisoft\Db\Sqlite\Tests\TestCase;

/**
 * @group sqlite
 */
final class InconditionBuilderTest extends TestCase
{
    public function testBuildSubqueryInCondition(): void
    {
        $db = $this->getConnection();
        $inCondition = new InCondition(
            ['id'],
            'in',
            (new Query($db))->select('id')->from('users')->where(['active' => 1]),
        );

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Builder\InConditionBuilder::buildSubqueryInCondition is not supported by SQLite.'
        );

        (new InConditionBuilder($db->getQueryBuilder()))->build($inCondition);
    }
}
