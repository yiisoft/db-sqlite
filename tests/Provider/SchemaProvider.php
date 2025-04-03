<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Schema\Column\BinaryColumn;
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
                    'int_col' => new IntegerColumn(
                        dbType: 'integer',
                        notNull: true,
                    ),
                    'int_col2' => new IntegerColumn(
                        dbType: 'integer',
                        defaultValue: 1,
                    ),
                    'tinyint_col' => new IntegerColumn(
                        ColumnType::TINYINT,
                        dbType: 'tinyint',
                        size: 3,
                        defaultValue: 1,
                    ),
                    'smallint_col' => new IntegerColumn(
                        ColumnType::SMALLINT,
                        dbType: 'smallint',
                        size: 1,
                        defaultValue: 1,
                    ),
                    'char_col' => new StringColumn(
                        ColumnType::CHAR,
                        dbType: 'char',
                        notNull: true,
                        size: 100,
                    ),
                    'char_col2' => new StringColumn(
                        dbType: 'varchar',
                        size: 100,
                        defaultValue: 'something"',
                    ),
                    'char_col3' => new StringColumn(
                        ColumnType::TEXT,
                        dbType: 'text',
                    ),
                    'float_col' => new DoubleColumn(
                        dbType: 'double',
                        notNull: true,
                        size: 4,
                        scale: 3,
                    ),
                    'float_col2' => new DoubleColumn(
                        dbType: 'double',
                        defaultValue: 1.23,
                    ),
                    'blob_col' => new BinaryColumn(
                        dbType: 'blob',
                    ),
                    'numeric_col' => new DoubleColumn(
                        ColumnType::DECIMAL,
                        dbType: 'decimal',
                        size: 5,
                        scale: 2,
                        defaultValue: 33.22,
                    ),
                    'timestamp_col' => new StringColumn(
                        ColumnType::TIMESTAMP,
                        dbType: 'timestamp',
                        notNull: true,
                        defaultValue: '2002-01-01 00:00:00',
                    ),
                    'bool_col' => new BooleanColumn(
                        dbType: 'tinyint',
                        notNull: true,
                        size: 1,
                    ),
                    'bool_col2' => new BooleanColumn(
                        dbType: 'tinyint',
                        size: 1,
                        defaultValue: true,
                    ),
                    'ts_default' => new StringColumn(
                        ColumnType::TIMESTAMP,
                        dbType: 'timestamp',
                        notNull: true,
                        defaultValue: new Expression('CURRENT_TIMESTAMP'),
                    ),
                    'bit_col' => new BitColumn(
                        dbType: 'bit',
                        notNull: true,
                        size: 8,
                        defaultValue: 0b1000_0010, // 130
                    ),
                    'json_col' => new JsonColumn(
                        dbType: 'json',
                        notNull: true,
                        defaultValue: ['number' => 10],
                    ),
                    'json_text_col' => new JsonColumn(
                        dbType: 'json',
                    ),
                ],
                'tableName' => 'type',
            ],
            [
                [
                    'id' => new IntegerColumn(
                        dbType: 'integer',
                        primaryKey: true,
                        notNull: true,
                        autoIncrement: true,
                    ),
                    'type' => new StringColumn(
                        dbType: 'varchar',
                        notNull: true,
                        size: 255,
                    ),
                ],
                'animal',
            ],
            [
                [
                    'id' => new IntegerColumn(
                        dbType: 'integer',
                        primaryKey: true,
                        autoIncrement: true,
                    ),
                    'text_col' => new StringColumn(
                        ColumnType::TEXT,
                        dbType: 'text',
                        notNull: true,
                        defaultValue: 'CURRENT_TIMESTAMP',
                    ),
                    'timestamp_text' => new StringColumn(
                        ColumnType::TEXT,
                        dbType: 'text',
                        notNull: true,
                        defaultValue: new Expression('CURRENT_TIMESTAMP'),
                    ),
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
                    'bit_col_1' => new BooleanColumn(
                        dbType: 'bit',
                        notNull: true,
                        size: 1,
                    ),
                    'bit_col_2' => new BooleanColumn(
                        dbType: 'bit',
                        size: 1,
                        defaultValue: true,
                    ),
                    'bit_col_3' => new BitColumn(
                        dbType: 'bit',
                        notNull: true,
                        size: 32,
                    ),
                    'bit_col_4' => new BitColumn(
                        dbType: 'bit',
                        size: 32,
                        defaultValue: 1,
                    ),
                    'bit_col_5' => new BitColumn(
                        dbType: 'bit',
                        notNull: true,
                        size: 64,
                    ),
                    'bit_col_6' => new BitColumn(
                        dbType: 'bit',
                        size: 64,
                        defaultValue: 1,
                    ),
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
}
