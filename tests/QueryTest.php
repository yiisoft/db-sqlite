<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQueryTest;

/**
 * @group sqlite
 */
final class QueryTest extends CommonQueryTest
{
    use TestTrait;

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testUnion(): void
    {
        $db = $this->getConnectionwithData();

        $query = $this->getQuery($db);
        $subQuery = $this->getQuery($db)->select(['id', 'name'])->from(['category']);
        $query->select(['id', 'name'])->from('item')->union($subQuery);
        $result = $query->all();

        $this->assertNotEmpty($result);
        $this->assertCount(7, $result);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testLimitOffsetWithExpression(): void
    {
        $db = $this->getConnectionWithData();

        $query = $this->getQuery($db)->from('customer')->select('id')->orderBy('id');
        $query->limit(new Expression('1 + 1'))->offset(new Expression('1 + 0'));
        $result = $query->column();

        $this->assertSame([2, 3], $result);
    }
}
