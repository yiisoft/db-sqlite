<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Column\ColumnBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\AbstractColumnBuilderTest;

/**
 * @group sqlite
 */
class ColumnBuilderTest extends AbstractColumnBuilderTest
{
    use TestTrait;

    public function getColumnBuilderClass(): string
    {
        return ColumnBuilder::class;
    }
}
