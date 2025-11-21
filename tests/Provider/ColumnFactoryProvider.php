<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Schema\Column\BigIntColumn;
use Yiisoft\Db\Schema\Column\BinaryColumn;
use Yiisoft\Db\Schema\Column\BitColumn;
use Yiisoft\Db\Schema\Column\BooleanColumn;
use Yiisoft\Db\Schema\Column\DatetimeColumn;
use Yiisoft\Db\Schema\Column\DoubleColumn;
use Yiisoft\Db\Schema\Column\EnumColumn;
use Yiisoft\Db\Schema\Column\IntegerColumn;
use Yiisoft\Db\Schema\Column\JsonColumn;
use Yiisoft\Db\Schema\Column\StringColumn;

final class ColumnFactoryProvider extends \Yiisoft\Db\Tests\Provider\ColumnFactoryProvider
{
    public static function dbTypes(): array
    {
        return [
            // db type, expected abstract type, expected instance of
            ['bool', ColumnType::BOOLEAN, BooleanColumn::class],
            ['boolean', ColumnType::BOOLEAN, BooleanColumn::class],
            ['bit', ColumnType::BIT, BitColumn::class],
            ['tinyint', ColumnType::TINYINT, IntegerColumn::class],
            ['smallint', ColumnType::SMALLINT, IntegerColumn::class],
            ['mediumint', ColumnType::INTEGER, IntegerColumn::class],
            ['int', ColumnType::INTEGER, IntegerColumn::class],
            ['integer', ColumnType::INTEGER, IntegerColumn::class],
            ['bigint', ColumnType::BIGINT, IntegerColumn::class],
            ['float', ColumnType::FLOAT, DoubleColumn::class],
            ['real', ColumnType::FLOAT, DoubleColumn::class],
            ['double', ColumnType::DOUBLE, DoubleColumn::class],
            ['decimal', ColumnType::DECIMAL, DoubleColumn::class],
            ['numeric', ColumnType::DECIMAL, DoubleColumn::class],
            ['char', ColumnType::CHAR, StringColumn::class],
            ['varchar', ColumnType::STRING, StringColumn::class],
            ['string', ColumnType::STRING, StringColumn::class],
            ['enum', ColumnType::ENUM, EnumColumn::class],
            ['tinytext', ColumnType::TEXT, StringColumn::class],
            ['mediumtext', ColumnType::TEXT, StringColumn::class],
            ['longtext', ColumnType::TEXT, StringColumn::class],
            ['text', ColumnType::TEXT, StringColumn::class],
            ['blob', ColumnType::BINARY, BinaryColumn::class],
            ['year', ColumnType::SMALLINT, IntegerColumn::class],
            ['date', ColumnType::DATE, DatetimeColumn::class],
            ['time', ColumnType::TIME, DatetimeColumn::class],
            ['timetz', ColumnType::TIMETZ, DatetimeColumn::class],
            ['datetime', ColumnType::DATETIME, DatetimeColumn::class],
            ['datetimetz', ColumnType::DATETIMETZ, DatetimeColumn::class],
            ['timestamp', ColumnType::TIMESTAMP, DatetimeColumn::class],
            ['json', ColumnType::JSON, JsonColumn::class],
        ];
    }

    public static function definitions(): array
    {
        $definitions = parent::definitions();

        $definitions[] = ['bit(1)', new BooleanColumn(dbType: 'bit', size: 1)];
        $definitions[] = ['tinyint(1)', new BooleanColumn(dbType: 'tinyint', size: 1)];

        return $definitions;
    }

    public static function pseudoTypes(): array
    {
        $types = parent::pseudoTypes();

        // SQLite doesn't support unsigned types
        $types['upk'][1] = new IntegerColumn(primaryKey: true, autoIncrement: true, unsigned: false);
        $types['ubigpk'][1] = new BigIntColumn(primaryKey: true, autoIncrement: true, unsigned: false);

        return $types;
    }

    public static function defaultValueRaw(): array
    {
        $defaultValueRaw = parent::defaultValueRaw();

        $defaultValueRaw[] = [ColumnType::STRING, '"str""ing"', 'str"ing'];

        return $defaultValueRaw;
    }
}
