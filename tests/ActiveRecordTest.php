<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Db\Sqlite\Tests;

/**
 * @group db
 * @group sqlite
 */
class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\Unit\ActiveRecordTest
{
    protected $driverName = 'sqlite';
}
