<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\TestUtility\TestQueryTrait;

/**
 * @group sqlite
 */
final class QueryTest extends TestCase
{
    use TestQueryTrait;

    public function testUnion(): void
    {
        $db = $this->getConnection();

        $query = new Query($db);

        $query
            ->select(['id', 'name'])
            ->from('item')
            ->union(
                (new Query($db))
                    ->select(['id', 'name'])
                    ->from(['category'])
            );

        $result = $query->all();

        $this->assertNotEmpty($result);
        $this->assertCount(7, $result);
    }

    public function testLimitOffsetWithExpression(): void
    {
        $query = (new Query($this->getConnection()))
            ->from('customer')
            ->select('id')
            ->orderBy('id');

        $query
            ->limit(new Expression('1 + 1'))
            ->offset(new Expression('1 + 0'));

        $result = $query->column();

        $this->assertCount(2, $result);
        $this->assertTrue(in_array('2', $result, false));
        $this->assertTrue(in_array('3', $result, false));
        $this->assertFalse(in_array('1', $result, false));
    }
}
