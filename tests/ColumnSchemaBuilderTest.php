<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonColumnSchemaBuilderTest;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ColumnSchemaBuilderTest extends CommonColumnSchemaBuilderTest
{
    use TestTrait;

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\ColumnSchemaBuilderProvider::types
     */
    public function testCustomTypes(string $expected, string $type, int|null $length, array $calls): void
    {
        $this->checkBuildString($expected, $type, $length, $calls);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\ColumnSchemaBuilderProvider::createColumnTypes
     */
    public function testCreateColumnTypes(string $expected, string $type, ?int $length, array $calls): void
    {
        parent::testCreateColumnTypes($expected, $type, $length, $calls);
    }
}
