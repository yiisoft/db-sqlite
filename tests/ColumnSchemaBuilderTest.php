<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\ColumnSchemaBuilder;
use Yiisoft\Db\Sqlite\PDO\SchemaPDOSqlite;
use Yiisoft\Db\TestSupport\TestColumnSchemaBuilderTrait;

/**
 * @group sqlite
 */
final class ColumnSchemaBuilderTest extends TestCase
{
    use TestColumnSchemaBuilderTrait;

    public function getColumnSchemaBuilder($type, $length = null): ColumnSchemaBuilder
    {
        return new ColumnSchemaBuilder($type, $length, $this->getConnection());
    }

    public function typesProvider(): array
    {
        return [
            ['integer UNSIGNED', SchemaPDOSqlite::TYPE_INTEGER, null, [
                ['unsigned'],
            ]],
            ['integer(10) UNSIGNED', SchemaPDOSqlite::TYPE_INTEGER, 10, [
                ['unsigned'],
            ]],
            ['integer(10)', SchemaPDOSqlite::TYPE_INTEGER, 10, [
                ['comment', 'test'],
            ]],
        ];
    }

    /**
     * @dataProvider typesProvider
     *
     * @param string $expected
     * @param string $type
     * @param int|null $length
     * @param mixed $calls
     */
    public function testCustomTypes(string $expected, string $type, ?int $length, $calls): void
    {
        $this->checkBuildString($expected, $type, $length, $calls);
    }
}
