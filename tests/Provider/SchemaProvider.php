<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Schema\Column\ArrayColumn;
use Yiisoft\Db\Schema\Column\BitColumn;
use Yiisoft\Db\Schema\Column\BooleanColumn;
use Yiisoft\Db\Schema\Column\DoubleColumn;
use Yiisoft\Db\Schema\Column\IntegerColumn;
use Yiisoft\Db\Schema\Column\JsonColumn;
use Yiisoft\Db\Schema\Column\StringColumn;
use Yiisoft\Db\Tests\Support\AnyValue;

final class SchemaProvider extends \Yiisoft\Db\Tests\Provider\SchemaProvider
{
    public static function columns(): array
    {
        return [
            [
                [
                    'int_col' => [
                        'type' => 'integer',
                        'dbType' => 'integer',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'int_col2' => [
                        'type' => 'integer',
                        'dbType' => 'integer',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => 1,
                    ],
                    'tinyint_col' => [
                        'type' => 'tinyint',
                        'dbType' => 'tinyint',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 3,
                        'scale' => null,
                        'defaultValue' => 1,
                    ],
                    'smallint_col' => [
                        'type' => 'smallint',
                        'dbType' => 'smallint',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 1,
                        'scale' => null,
                        'defaultValue' => 1,
                    ],
                    'char_col' => [
                        'type' => 'char',
                        'dbType' => 'char',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 100,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'char_col2' => [
                        'type' => 'string',
                        'dbType' => 'varchar',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 100,
                        'scale' => null,
                        'defaultValue' => 'something"',
                    ],
                    'char_col3' => [
                        'type' => 'text',
                        'dbType' => 'text',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'float_col' => [
                        'type' => 'double',
                        'dbType' => 'double',
                        'phpType' => 'float',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 4,
                        'scale' => 3,
                        'defaultValue' => null,
                    ],
                    'float_col2' => [
                        'type' => 'double',
                        'dbType' => 'double',
                        'phpType' => 'float',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => 1.23,
                    ],
                    'blob_col' => [
                        'type' => 'binary',
                        'dbType' => 'blob',
                        'phpType' => 'mixed',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'numeric_col' => [
                        'type' => 'decimal',
                        'dbType' => 'decimal',
                        'phpType' => 'float',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 5,
                        'scale' => 2,
                        'defaultValue' => 33.22,
                    ],
                    'timestamp_col' => [
                        'type' => 'timestamp',
                        'dbType' => 'timestamp',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => '2002-01-01 00:00:00',
                    ],
                    'bool_col' => [
                        'type' => 'boolean',
                        'dbType' => 'tinyint',
                        'phpType' => 'bool',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 1,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'bool_col2' => [
                        'type' => 'boolean',
                        'dbType' => 'tinyint',
                        'phpType' => 'bool',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 1,
                        'scale' => null,
                        'defaultValue' => true,
                    ],
                    'ts_default' => [
                        'type' => 'timestamp',
                        'dbType' => 'timestamp',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => new Expression('CURRENT_TIMESTAMP'),
                    ],
                    'bit_col' => [
                        'type' => 'bit',
                        'dbType' => 'bit',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 8,
                        'scale' => null,
                        'defaultValue' => 0b1000_0010, // 130
                    ],
                    'json_col' => [
                        'type' => 'json',
                        'dbType' => 'json',
                        'phpType' => 'mixed',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => ['number' => 10],
                    ],
                    'json_text_col' => [
                        'type' => 'json',
                        'dbType' => 'json',
                        'phpType' => 'mixed',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                ],
                'tableName' => 'type',
            ],
            [
                [
                    'id' => [
                        'type' => 'integer',
                        'dbType' => 'integer',
                        'phpType' => 'int',
                        'primaryKey' => true,
                        'notNull' => true,
                        'autoIncrement' => true,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'type' => [
                        'type' => 'string',
                        'dbType' => 'varchar',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 255,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                ],
                'animal',
            ],
            [
                [
                    'id' => [
                        'type' => 'integer',
                        'dbType' => 'integer',
                        'phpType' => 'int',
                        'primaryKey' => true,
                        'notNull' => false,
                        'autoIncrement' => true,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'text_col' => [
                        'type' => 'text',
                        'dbType' => 'text',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => 'CURRENT_TIMESTAMP',
                    ],
                    'timestamp_text' => [
                        'type' => 'text',
                        'dbType' => 'text',
                        'phpType' => 'string',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => null,
                        'scale' => null,
                        'defaultValue' => new Expression('CURRENT_TIMESTAMP'),
                    ],
                ],
                'timestamp_default',
            ],
        ];
    }

    public static function columnsTypeBit(): array
    {
        return [
            [
                [
                    'bit_col_1' => [
                        'type' => 'boolean',
                        'dbType' => 'bit',
                        'phpType' => 'bool',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 1,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'bit_col_2' => [
                        'type' => 'boolean',
                        'dbType' => 'bit',
                        'phpType' => 'bool',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 1,
                        'scale' => null,
                        'defaultValue' => true,
                    ],
                    'bit_col_3' => [
                        'type' => 'bit',
                        'dbType' => 'bit',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 32,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'bit_col_4' => [
                        'type' => 'bit',
                        'dbType' => 'bit',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 32,
                        'scale' => null,
                        'defaultValue' => 1,
                    ],
                    'bit_col_5' => [
                        'type' => 'bit',
                        'dbType' => 'bit',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => true,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 64,
                        'scale' => null,
                        'defaultValue' => null,
                    ],
                    'bit_col_6' => [
                        'type' => 'bit',
                        'dbType' => 'bit',
                        'phpType' => 'int',
                        'primaryKey' => false,
                        'notNull' => false,
                        'autoIncrement' => false,
                        'enumValues' => null,
                        'size' => 64,
                        'scale' => null,
                        'defaultValue' => 1,
                    ],
                ],
            ],
        ];
    }

    public static function constraints(): array
    {
        $constraints = parent::constraints();

        $constraints['1: primary key'][2]->name(null);
        $constraints['1: check'][2][0]->columnNames(null);
        $constraints['1: check'][2][0]->expression('"C_check" <> \'\'');
        $constraints['1: unique'][2][0]->name(AnyValue::getInstance());
        $constraints['1: index'][2][1]->name(AnyValue::getInstance());

        $constraints['2: primary key'][2]->name(null);
        $constraints['2: unique'][2][0]->name(AnyValue::getInstance());
        $constraints['2: index'][2][2]->name(AnyValue::getInstance());

        $constraints['3: foreign key'][2][0]->name('0');
        $constraints['3: index'][2] = [];

        $constraints['4: primary key'][2]->name(null);
        $constraints['4: unique'][2][0]->name(AnyValue::getInstance());

        return $constraints;
    }

    public static function resultColumns(): array
    {
        return [
            [null, []],
            [null, ['native_type' => 'null']],
            [new IntegerColumn(dbType: 'integer', name: 'int_col'), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'INTEGER',
                'table' => 'type',
                'flags' => [],
                'name' => 'int_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new IntegerColumn(ColumnType::TINYINT, dbType: 'tinyint', name: 'tinyint_col', size: 3), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'TINYINT(3)',
                'table' => 'type',
                'flags' => [],
                'name' => 'tinyint_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new StringColumn(dbType: 'varchar', name: 'char_col2', size: 100), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'varchar(100)',
                'table' => 'type',
                'flags' => [],
                'name' => 'char_col2',
                'len' => -1,
                'precision' => 0,
            ]],
            [new DoubleColumn(dbType: 'double', name: 'float_col', size: 4, scale: 3), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'double(4,3)',
                'table' => 'type',
                'flags' => [],
                'name' => 'float_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new StringColumn(ColumnType::TIMESTAMP, dbType: 'timestamp', name: 'timestamp_col'), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'timestamp',
                'table' => 'type',
                'flags' => [],
                'name' => 'timestamp_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new BooleanColumn(dbType: 'tinyint', name: 'bool_col', size: 1), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'tinyint(1)',
                'table' => 'type',
                'flags' => [],
                'name' => 'bool_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new BitColumn(dbType: 'bit', name: 'bit_col', size: 8), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'BIT(8)',
                'table' => 'type',
                'flags' => [],
                'name' => 'bit_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new JsonColumn(dbType: 'json', name: 'json_col'), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'json',
                'table' => 'type',
                'flags' => [],
                'name' => 'json_col',
                'len' => -1,
                'precision' => 0,
            ]],
            [new ArrayColumn(dbType: 'int', name: 'int_arr', dimension: 1, column: new IntegerColumn(dbType: 'int', name: 'int_arr')), [
                'native_type' => 'null',
                'pdo_type' => 0,
                'sqlite:decl_type' => 'int[]',
                'table' => 'type',
                'flags' => [],
                'name' => 'int_arr',
                'len' => -1,
                'precision' => 0,
            ]],
            [new IntegerColumn(dbType: 'integer', name: '1'), [
                'native_type' => 'integer',
                'pdo_type' => 1,
                'flags' => [],
                'name' => '1',
                'len' => -1,
                'precision' => 0,
            ]],
            [new DoubleColumn(dbType: 'double', name: '2.5'), [
                'native_type' => 'double',
                'pdo_type' => 2,
                'flags' => [],
                'name' => '2.5',
                'len' => -1,
                'precision' => 0,
            ]],
            [new StringColumn(name: 'string'), [
                'native_type' => 'string',
                'pdo_type' => 2,
                'flags' => [],
                'name' => 'string',
                'len' => -1,
                'precision' => 0,
            ]],
        ];
    }
}
