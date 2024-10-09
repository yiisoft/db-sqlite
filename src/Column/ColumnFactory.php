<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Schema\Column\AbstractColumnFactory;

final class ColumnFactory extends AbstractColumnFactory
{
    /**
     * Mapping from physical column types (keys) to abstract column types (values).
     *
     * @var string[]
     *
     * @psalm-suppress MissingClassConstType
     */
    private const TYPE_MAP = [
        'bool' => ColumnType::BOOLEAN,
        'boolean' => ColumnType::BOOLEAN,
        'bit' => ColumnType::BIT,
        'tinyint' => ColumnType::TINYINT,
        'smallint' => ColumnType::SMALLINT,
        'mediumint' => ColumnType::INTEGER,
        'int' => ColumnType::INTEGER,
        'integer' => ColumnType::INTEGER,
        'bigint' => ColumnType::BIGINT,
        'float' => ColumnType::FLOAT,
        'real' => ColumnType::FLOAT,
        'double' => ColumnType::DOUBLE,
        'decimal' => ColumnType::DECIMAL,
        'numeric' => ColumnType::DECIMAL,
        'char' => ColumnType::CHAR,
        'varchar' => ColumnType::STRING,
        'enum' => ColumnType::STRING,
        'tinytext' => ColumnType::TEXT,
        'mediumtext' => ColumnType::TEXT,
        'longtext' => ColumnType::TEXT,
        'text' => ColumnType::TEXT,
        'blob' => ColumnType::BINARY,
        'year' => ColumnType::DATE,
        'date' => ColumnType::DATE,
        'time' => ColumnType::TIME,
        'datetime' => ColumnType::DATETIME,
        'timestamp' => ColumnType::TIMESTAMP,
        'json' => ColumnType::JSON,
    ];

    protected function getType(string $dbType, array $info = []): string
    {
        $type = self::TYPE_MAP[$dbType] ?? ColumnType::STRING;

        if (
            ($type === ColumnType::BIT || $type === ColumnType::TINYINT)
            && isset($info['size'])
            && $info['size'] === 1
        ) {
            return ColumnType::BOOLEAN;
        }

        return $type;
    }

    protected function isDbType(string $dbType): bool
    {
        return isset(self::TYPE_MAP[$dbType]);
    }
}
