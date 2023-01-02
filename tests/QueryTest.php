<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQueryTest;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class QueryTest extends CommonQueryTest
{
    use TestTrait;

    /**
     * Ensure no ambiguous column error occurs on indexBy with JOIN.
     *
     * @link https://github.com/yiisoft/yii2/issues/13859
     */
    public function testAmbiguousColumnIndexBy(): void
    {
        $db = $this->getConnection(true);

        $selectExpression = "(customer.name || ' in ' || p.description) AS name";

        $result = (new Query($db))
            ->select([$selectExpression])
            ->from('customer')
            ->innerJoin('profile p', '[[customer]].[[profile_id]] = [[p]].[[id]]')
            ->indexBy('id')
            ->column();

        $this->assertSame([1 => 'user1 in profile customer 1', 3 => 'user3 in profile customer 3'], $result);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testLimitOffsetWithExpression(): void
    {
        $db = $this->getConnection(true);

        $query = (new Query($db))->from('customer')->select('id')->orderBy('id');
        $query->limit(new Expression('1 + 1'))->offset(new Expression('1 + 0'));
        $result = $query->column();

        $this->assertContains('2', $result);
        $this->assertContains('3', $result);
        $this->assertNotContains('1', $result);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testUnion(): void
    {
        $db = $this->getConnection(true);

        $query = new Query($db);
        $query->select(['id', 'name'])
            ->from('item')
            ->union((new Query($db))->select(['id', 'name'])->from(['category']));
        $result = $query->all();
        $this->assertNotEmpty($result);
        $this->assertCount(7, $result);
    }
}
