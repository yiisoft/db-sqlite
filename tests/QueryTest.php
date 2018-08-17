<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Query;

/**
 * @group db
 * @group sqlite
 */
class QueryTest extends \Yiisoft\Db\Tests\QueryTest
{
    protected $driverName = 'sqlite';

    public function testUnion()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $query->select(['id', 'name'])
            ->from('item')
            ->union(
                (new Query())
                    ->select(['id', 'name'])
                    ->from(['category'])
            );
        $result = $query->all($connection);
        $this->assertNotEmpty($result);
        $this->assertCount(7, $result);
    }
}
