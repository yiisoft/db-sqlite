<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Command\Param;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Sqlite\ColumnSchema;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Query\Query;

use function str_repeat;

/**
 * @group sqlite
 */
final class ColumnSchemaTest extends TestCase
{
    use TestTrait;

    public function testPhpTypeCast(): void
    {
        $db = $this->getConnection(true);

        $command = $db->createCommand();
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema('type');

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
        $query = (new Query($db))->from('type')->one();

        $this->assertNotNull($tableSchema);

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

    public function testTypeCastJson(): void
    {
        $columnSchema = new ColumnSchema('json_col');
        $columnSchema->dbType(SchemaInterface::TYPE_JSON);
        $columnSchema->type(SchemaInterface::TYPE_JSON);

        $this->assertSame(['a' => 1], $columnSchema->phpTypeCast('{"a":1}'));
        $this->assertEquals(new JsonExpression(['a' => 1], SchemaInterface::TYPE_JSON), $columnSchema->dbTypeCast(['a' => 1]));
    }
}
