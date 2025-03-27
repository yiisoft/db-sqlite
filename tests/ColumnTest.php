<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Yiisoft\Db\Command\Param;
use Yiisoft\Db\Schema\Column\BinaryColumn;
use Yiisoft\Db\Schema\Column\BooleanColumn;
use Yiisoft\Db\Schema\Column\DoubleColumn;
use Yiisoft\Db\Schema\Column\IntegerColumn;
use Yiisoft\Db\Schema\Column\JsonColumn;
use Yiisoft\Db\Schema\Column\StringColumn;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Tests\AbstractColumnTest;

use function str_repeat;

/**
 * @group sqlite
 */
final class ColumnTest extends AbstractColumnTest
{
    use TestTrait;

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
                'bool_col' => false,
                'bit_col' => 0b0110_0110, // 102
                'json_col' => [['a' => 1, 'b' => null, 'c' => [1, 3, 5]]],
                'json_text_col' => (new Query($db))->select(new Param('[1,2,3,"string",null]', PDO::PARAM_STR)),
            ]
        );
        $command->execute();
    }

    private function assertResultValues(array $result): void
    {
        $this->assertSame(1, $result['int_col']);
        $this->assertSame(str_repeat('x', 100), $result['char_col']);
        $this->assertNull($result['char_col3']);
        $this->assertSame(1.234, $result['float_col']);
        $this->assertSame("\x10\x11\x12", $result['blob_col']);
        $this->assertSame('2023-07-11 14:50:23', $result['timestamp_col']);
        $this->assertFalse($result['bool_col']);
        $this->assertSame(0b0110_0110, $result['bit_col']);
        $this->assertSame([['a' => 1, 'b' => null, 'c' => [1, 3, 5]]], $result['json_col']);
        $this->assertSame('[1,2,3,"string",null]', $result['json_text_col']);
    }

    public function testQueryTypecasting(): void
    {
        $db = $this->getConnection(true);

        $this->insertTypeValues($db);

        $result = (new Query($db))->typecasting()->from('type')->one();

        $this->assertResultValues($result);

        $db->close();
    }

    public function testCommandPhpTypecasting(): void
    {
        $db = $this->getConnection(true);

        $this->insertTypeValues($db);

        $result = $db->createCommand('SELECT * FROM type')->phpTypecasting()->queryOne();

        $this->assertResultValues($result);

        $db->close();
    }

    public function testSelectPhpTypecasting(): void
    {
        $db = $this->getConnection();

        $result = $db->createCommand("SELECT null, 1, 2.5, true, false, 'string'")->phpTypecasting()->queryOne();

        $this->assertSame([
            'null' => null,
            1 => 1,
            '2.5' => 2.5,
            'true' => 1,
            'false' => 0,
            '\'string\'' => 'string',
        ], $result);

        $result = $db->createCommand('SELECT 2.5')->phpTypecasting()->queryScalar();

        $this->assertSame(2.5, $result);

        $db->close();
    }

    public function testPhpTypeCast(): void
    {
        $db = $this->getConnection(true);
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema('type');

        $this->insertTypeValues($db);

        $query = (new Query($db))->from('type')->one();

        $intColPhpType = $tableSchema->getColumn('int_col')?->phpTypecast($query['int_col']);
        $charColPhpType = $tableSchema->getColumn('char_col')?->phpTypecast($query['char_col']);
        $charCol3PhpType = $tableSchema->getColumn('char_col3')?->phpTypecast($query['char_col3']);
        $floatColPhpType = $tableSchema->getColumn('float_col')?->phpTypecast($query['float_col']);
        $blobColPhpType = $tableSchema->getColumn('blob_col')?->phpTypecast($query['blob_col']);
        $timestampColPhpType = $tableSchema->getColumn('timestamp_col')?->phpTypecast($query['timestamp_col']);
        $boolColPhpType = $tableSchema->getColumn('bool_col')?->phpTypecast($query['bool_col']);
        $bitColPhpType = $tableSchema->getColumn('bit_col')?->phpTypecast($query['bit_col']);
        $jsonColPhpType = $tableSchema->getColumn('json_col')?->phpTypecast($query['json_col']);
        $jsonTextColPhpType = $tableSchema->getColumn('json_text_col')?->phpTypecast($query['json_text_col']);

        $this->assertSame(1, $intColPhpType);
        $this->assertSame(str_repeat('x', 100), $charColPhpType);
        $this->assertNull($charCol3PhpType);
        $this->assertSame(1.234, $floatColPhpType);
        $this->assertSame("\x10\x11\x12", $blobColPhpType);
        $this->assertSame('2023-07-11 14:50:23', $timestampColPhpType);
        $this->assertFalse($boolColPhpType);
        $this->assertSame(0b0110_0110, $bitColPhpType);
        $this->assertSame([['a' => 1, 'b' => null, 'c' => [1, 3, 5]]], $jsonColPhpType);
        $this->assertSame([1, 2, 3, 'string', null], $jsonTextColPhpType);

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
