<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Schema\ColumnSchemaBuilder;
use Yiisoft\Db\Sqlite\Schema\Schema;
use Yiisoft\Db\Tests\ColumnSchemaBuilderTest as AbstractColumnSchemaBuilderTest;

class ColumnSchemaBuilderTest extends AbstractColumnSchemaBuilderTest
{
    protected ?string $driverName = 'sqlite';

    /**
     * @param string $type
     * @param int $length
     *
     * @return ColumnSchemaBuilder
     */
    public function getColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length, $this->getConnection());
    }

    /**
     * @return array
     */
    public function typesProvider(): array
    {
        return [
            ['integer UNSIGNED', Schema::TYPE_INTEGER, null, [
                ['unsigned'],
            ]],
            ['integer(10) UNSIGNED', Schema::TYPE_INTEGER, 10, [
                ['unsigned'],
            ]],
            // comments are ignored
            ['integer(10)', Schema::TYPE_INTEGER, 10, [
                ['comment', 'test'],
            ]],
        ];
    }
}
