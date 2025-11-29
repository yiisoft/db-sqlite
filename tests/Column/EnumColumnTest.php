<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Column;

use PHPUnit\Framework\Attributes\TestWith;
use Yiisoft\Db\Schema\Column\EnumColumn;
use Yiisoft\Db\Sqlite\Tests\Support\IntegrationTestTrait;
use Yiisoft\Db\Tests\Common\CommonEnumColumnTest;

final class EnumColumnTest extends CommonEnumColumnTest
{
    use IntegrationTestTrait;

    #[TestWith(['INTEGER CHECK (status IN (1, 2, 3))'])]
    #[TestWith(["TEXT CHECK (status != 'abc')"])]
    #[TestWith(["TEXT CHECK (status NOT IN ('a', 'b', 'c'))"])]
    #[TestWith(["TEXT CHECK (status not IN ('a', 'b', 'c'))"])]
    public function testNonEnumCheck(string $columnDefinition): void
    {
        $this->dropTable('test_enum_table');
        $this->executeStatements(
            <<<SQL
            CREATE TABLE test_enum_table (
                id INTEGER,
                status $columnDefinition
            )
            SQL,
        );

        $db = $this->getSharedConnection();
        $column = $db->getTableSchema('test_enum_table')->getColumn('status');

        $this->assertNotInstanceOf(EnumColumn::class, $column);

        $this->dropTable('test_enum_table');
    }

    #[TestWith([
        'knot',
        "TEXT CHECK (knot IN ('a', 'b'))",
        ['a', 'b'],
    ])]
    public function testEnumCheck(string $columnName, string $columnDefinition, array $expectedValues): void
    {
        $this->dropTable('test_enum_table');

        $quotedColumnName = $this->getSharedConnection()->getQuoter()->quoteColumnName($columnName);
        $this->executeStatements(
            <<<SQL
            CREATE TABLE test_enum_table (
                id INTEGER,
                $quotedColumnName $columnDefinition
            )
            SQL,
        );

        $db = $this->getSharedConnection();
        $column = $db->getTableSchema('test_enum_table')->getColumn($columnName);

        $this->assertInstanceOf(EnumColumn::class, $column, $column::class);
        $this->assertEqualsCanonicalizing($expectedValues, $column->getValues());

        $this->dropTable('test_enum_table');
    }

    protected function createDatabaseObjectsStatements(): array
    {
        return [
            <<<SQL
            CREATE TABLE tbl_enum (
                id INTEGER,
                status TEXT CHECK(status IN ('active', 'unactive', 'pending'))
            )
            SQL,
        ];
    }

    protected function dropDatabaseObjectsStatements(): array
    {
        return [
            'DROP TABLE IF EXISTS tbl_enum',
        ];
    }
}
