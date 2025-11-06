<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\Value\Param;
use Yiisoft\Db\Schema\Column\BinaryColumn;
use Yiisoft\Db\Schema\Column\BooleanColumn;
use Yiisoft\Db\Schema\Column\DoubleColumn;
use Yiisoft\Db\Schema\Column\IntegerColumn;
use Yiisoft\Db\Schema\Column\JsonColumn;
use Yiisoft\Db\Schema\Column\StringColumn;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Tests\Common\CommonColumnTest;

use function iterator_to_array;
use function str_repeat;

/**
 * @group sqlite
 */
final class ColumnTest extends CommonColumnTest
{
    use TestTrait;

    public function testSelectWithPhpTypecasting(): void
    {
        $db = $this->getConnection();

        $sql = "SELECT null, 1, 2.5, true, false, 'string'";
        $expected = [
            'null' => null,
            1 => 1,
            '2.5' => 2.5,
            'true' => 1,
            'false' => 0,
            "'string'" => 'string',
        ];

        $result = $db->createCommand($sql)
            ->withPhpTypecasting()
            ->queryOne();

        $this->assertSame($expected, $result);

        $result = $db->createCommand($sql)
            ->withPhpTypecasting()
            ->query();

        $this->assertSame([$expected], iterator_to_array($result));

        $result = $db->createCommand('SELECT 2.5')
            ->withPhpTypecasting()
            ->queryScalar();

        $this->assertSame(2.5, $result);

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

    protected function insertTypeValues(ConnectionInterface $db): void
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
            ],
        );
        $command->execute();
    }

    protected function assertTypecastedValues(array $result, bool $allTypecasted = false): void
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
}
