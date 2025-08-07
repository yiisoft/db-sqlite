<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\Sqlite\Builder\InBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;

/**
 * @group sqlite
 */
final class InBuilderTest extends TestCase
{
    use TestTrait;

    public function testBuildSubqueryInCondition(): void
    {
        $db = $this->getConnection();
        $inCondition = new In(
            ['id'],
            (new Query($db))->select('id')->from('users')->where(['active' => 1]),
        );

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Builder\InBuilder::buildSubqueryInCondition is not supported by SQLite.'
        );

        (new InBuilder($db->getQueryBuilder()))->build($inCondition);
    }
}
