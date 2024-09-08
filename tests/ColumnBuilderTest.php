<?php

declare(strict_types=1);

use Yiisoft\Db\Sqlite\Column\ColumnFactory;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\AbstractColumnBuilderTest;

/**
 * @group sqlite
 */
class ColumnBuilderTest extends AbstractColumnBuilderTest
{
    use TestTrait;

    public function testColumnFactory(): void
    {
        $db = $this->getConnection();
        $columnBuilderClass = $db->getColumnBuilderClass();

        $this->assertInstanceOf(ColumnFactory::class, $columnBuilderClass::columnFactory());
    }
}
