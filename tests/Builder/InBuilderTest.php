<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Builder;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\Sqlite\Builder\InBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\IntegrationTestTrait;
use Yiisoft\Db\Tests\Support\IntegrationTestCase;

/**
 * @group sqlite
 */
final class InBuilderTest extends IntegrationTestCase
{
    use IntegrationTestTrait;

    public function testBuildSubqueryInCondition(): void
    {
        $db = $this->getSharedConnection();

        $inCondition = new In(
            ['id'],
            (new Query($db))->select('id')->from('users')->where(['active' => 1]),
        );

        $builder = new InBuilder($db->getQueryBuilder());

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Builder\InBuilder::buildSubqueryInCondition is not supported by SQLite.',
        );

        $builder->build($inCondition);
    }
}
