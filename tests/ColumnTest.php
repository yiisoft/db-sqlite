<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Yiisoft\Db\Command\Param;
use Yiisoft\Db\Schema\Column\BinaryColumn;
use Yiisoft\Db\Schema\Column\BooleanColumn;
use Yiisoft\Db\Schema\Column\DoubleColumn;
use Yiisoft\Db\Schema\Column\IntegerColumn;
use Yiisoft\Db\Schema\Column\JsonColumn;
use Yiisoft\Db\Schema\Column\StringColumn;
use Yiisoft\Db\Sqlite\Column\ColumnBuilder;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Tests\Common\CommonColumnTest;

use function str_repeat;

/**
 * @group sqlite
 */
final class ColumnTest extends CommonColumnTest
{
    use TestTrait;

    protected const COLUMN_BUILDER = ColumnBuilder::class;

    private function insertTypeValues(Connection $db): void
    {
        $command = $db->createCommand();

        $command->insert(
            'type',
            [
                'int_col' => 1,
                'char_col' => str_repeat('x', 100),
                'char_col3' => null,
                'float_col' => 1.234,
                'blob_col' => "\x10\x11\x12",
                'timestamp_col' => '2023-07-11 14:50:23',
                'timestamp_default' => new DateTimeImmutable('2023-07-11 14:50:23'),
                'bool_col' => false,
                'bit_col' => 0b0110_0110, // 102
                'json_col' => [['a' => 1, 'b' => null, 'c' => [1, 3, 5]]],
                'json_text_col' => (new Query($db))->select(new Param('[1,2,3,"string",null]', PDO::PARAM_STR)),
            ]
        );
        $command->execute();
    }

    private function assertTypecastedValues(array $result, bool $allTypecasted = false): void
    {
        $this->assertSame(1, $result['int_col']);
        $this->assertSame(str_repeat('x', 100), $result['char_col']);
        $this->assertNull($result['char_col3']);
        $this->assertSame(1.234, $result['float_col']);
        $this->assertSame("\x10\x11\x12", $result['blob_col']);
        $this->assertEquals(new DateTimeImmutable('2023-07-11 14:50:23', new DateTimeZone('UTC')), $result['timestamp_col']);
        $this->assertEquals(new DateTimeImmutable('2023-07-11 14:50:23'), $result['timestamp_default']);
        $this->assertFalse($result['bool_col']);
        $this->assertSame(0b0110_0110, $result['bit_col']);
        $this->assertSame([['a' => 1, 'b' => null, 'c' => [1, 3, 5]]], $result['json_col']);

        if ($allTypecasted) {
            $this->assertSame([1, 2, 3, 'string', null], $result['json_text_col']);
        } else {
            $this->assertSame('[1,2,3,"string",null]', $result['json_text_col']);
        }
    }

    public function testQueryWithTypecasting(): void
    {
        $db = $this->getConnection(true);

        $this->insertTypeValues($db);

        $query = (new Query($db))->from('type')->withTypecasting();

        $result = $query->one();

        $this->assertTypecastedValues($result);

        $result = $query->all();

        $this->assertTypecastedValues($result[0]);

        $db->close();
    }

    public function testCommandWithPhpTypecasting(): void
    {
        $db = $this->getConnection(true);

        $this->insertTypeValues($db);

        $command = $db->createCommand('SELECT * FROM type')->withPhpTypecasting();

        $result = $command->queryOne();

        $this->assertTypecastedValues($result);

        $result = $command->queryAll();

        $this->assertTypecastedValues($result[0]);

        $db->close();
    }

    public function testSelectWithPhpTypecasting(): void
    {
        $db = $this->getConnection();

        $result = $db->createCommand("SELECT null, 1, 2.5, true, false, 'string'")
            ->withPhpTypecasting()
            ->queryOne();

        $this->assertSame([
            'null' => null,
            1 => 1,
            '2.5' => 2.5,
            'true' => 1,
            'false' => 0,
            '\'string\'' => 'string',
        ], $result);

        $result = $db->createCommand('SELECT 2.5')
            ->withPhpTypecasting()
            ->queryScalar();

        $this->assertSame(2.5, $result);

        $db->close();
    }

    public function testPhpTypeCast(): void
    {
        $db = $this->getConnection(true);
        $schema = $db->getSchema();
        $columns = $schema->getTableSchema('type')->getColumns();

        $this->insertTypeValues($db);

        $query = (new Query($db))->from('type')->one();

        $result = [];

        foreach ($columns as $columnName => $column) {
            $result[$columnName] = $column->phpTypecast($query[$columnName]);
        }

        $this->assertTypecastedValues($result, true);

        $db->close();
    }

    public function testColumnInstance()
    {
        $db = $this->getConnection(true);
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema('type');

        $this->assertInstanceOf(IntegerColumn::class, $tableSchema->getColumn('int_col'));
        $this->assertInstanceOf(StringColumn::class, $tableSchema->getColumn('char_col'));
        $this->assertInstanceOf(DoubleColumn::class, $tableSchema->getColumn('float_col'));
        $this->assertInstanceOf(BinaryColumn::class, $tableSchema->getColumn('blob_col'));
        $this->assertInstanceOf(BooleanColumn::class, $tableSchema->getColumn('bool_col'));
        $this->assertInstanceOf(JsonColumn::class, $tableSchema->getColumn('json_col'));
    }
}
