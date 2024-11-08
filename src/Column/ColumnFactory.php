<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Schema\Column\AbstractColumnFactory;
use Yiisoft\Db\Schema\Column\ColumnSchemaInterface;

use function str_replace;
use function substr;

final class ColumnFactory extends AbstractColumnFactory
{
    /**
     * Mapping from physical column types (keys) to abstract column types (values).
     *
     * @var string[]
     * @psalm-var array<string, ColumnType::*>
     */
    protected const TYPE_MAP = [
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
        return match ($dbType) {
            'bit', 'tinyint' => isset($info['size']) && $info['size'] === 1
                ? ColumnType::BOOLEAN
                : parent::getType($dbType, $info),
            default => parent::getType($dbType, $info),
        };
    }

    protected function normalizeNotNullDefaultValue(string $defaultValue, ColumnSchemaInterface $column): mixed
    {
        if ($defaultValue[0] === '"' && $defaultValue[-1] === '"') {
            $value = substr($defaultValue, 1, -1);
            $value = str_replace('""', '"', $value);

            return $column->phpTypecast($value);
        }

        return parent::normalizeNotNullDefaultValue($defaultValue, $column);
    }
}
