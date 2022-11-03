<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\ColumnSchemaBuilder;
use Yiisoft\Db\Sqlite\Schema;
use Yiisoft\Db\TestSupport\TestColumnSchemaBuilderTrait;

/**
 * @group sqlite
 */
final class ColumnSchemaBuilderTest extends TestCase
{
    use TestColumnSchemaBuilderTrait;

    public function getColumnSchemaBuilder($type, $length = null): ColumnSchemaBuilder
    {
        return new ColumnSchemaBuilder($type, $length);
    }

    public function typesProvider(): array
    {
        return [
            ['integer UNSIGNED', Schema::TYPE_INTEGER, null, [['unsigned']]],
            ['integer(10) UNSIGNED', Schema::TYPE_INTEGER, 10, [['unsigned']]],
            ['integer(10)', Schema::TYPE_INTEGER, 10, [['comment', 'test']]],
            ['smallint UNSIGNED', Schema::TYPE_SMALLINT, null, [['unsigned']]],
            ['bigint UNSIGNED', Schema::TYPE_BIGINT, null, [['unsigned']]],
        ];
    }

    /**
     * @dataProvider typesProvider
     */
    public function testCustomTypes(string $expected, string $type, ?int $length, mixed $calls): void
    {
        $this->checkBuildString($expected, $type, $length, $calls);
    }
}
