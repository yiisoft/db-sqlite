<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\AbstractColumnFactoryTest;

/**
 * @group sqlite
 */
final class ColumnFactoryTest extends AbstractColumnFactoryTest
{
    use TestTrait;

    /** @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\ColumnFactoryProvider::dbTypes */
    public function testFromDbType(string $dbType, string $expectedType, string $expectedInstanceOf): void
    {
        parent::testFromDbType($dbType, $expectedType, $expectedInstanceOf);
    }

    /** @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\ColumnFactoryProvider::definitions */
    public function testFromDefinition(string $definition, string $expectedType, string $expectedInstanceOf, array $expectedInfo = []): void
    {
        parent::testFromDefinition($definition, $expectedType, $expectedInstanceOf, $expectedInfo);
    }

    /** @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\ColumnFactoryProvider::types */
    public function testFromType(string $type, string $expectedType, string $expectedInstanceOf): void
    {
        parent::testFromType($type, $expectedType, $expectedInstanceOf);
    }
}
