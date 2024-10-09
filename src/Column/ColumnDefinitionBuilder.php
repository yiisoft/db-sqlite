<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\QueryBuilder\AbstractColumnDefinitionBuilder;
use Yiisoft\Db\Schema\Column\ColumnSchemaInterface;

final class ColumnDefinitionBuilder extends AbstractColumnDefinitionBuilder
{
    protected const AUTO_INCREMENT_KEYWORD = 'AUTOINCREMENT';

    protected const CLAUSES = [
        'type',
        'primary_key',
        'auto_increment',
        'unique',
        'not_null',
        'default',
        'check',
        'references',
        'extra',
        'comment',
    ];

    protected const TYPES_WITH_SIZE = [
        'bit',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'integer',
        'bigint',
        'float',
        'real',
        'double',
        'decimal',
        'numeric',
        'char',
        'varchar',
        'text',
        'blob',
        'time',
        'datetime',
        'timestamp',
    ];

    protected const TYPES_WITH_SCALE = [
        'float',
        'real',
        'double',
        'decimal',
        'numeric',
    ];

    protected function buildComment(ColumnSchemaInterface $column): string
    {
        $comment = $column->getComment();

        return $comment === null || $comment === '' ? '' : ' /* ' . str_replace('*/', '*\/', $comment) . ' */';
    }

    protected function buildNotNull(ColumnSchemaInterface $column): string
    {
        return $column->isPrimaryKey() ? ' NOT NULL' : parent::buildNotNull($column);
    }

    protected function getDbType(ColumnSchemaInterface $column): string
    {
        /** @psalm-suppress DocblockTypeContradiction */
        return match ($column->getType()) {
            ColumnType::BOOLEAN => 'boolean',
            ColumnType::BIT => 'bit',
            ColumnType::TINYINT => $column->isAutoIncrement() ? 'integer' : 'tinyint',
            ColumnType::SMALLINT => $column->isAutoIncrement() ? 'integer' : 'smallint',
            ColumnType::INTEGER => 'integer',
            ColumnType::BIGINT => $column->isAutoIncrement() ? 'integer' : 'bigint',
            ColumnType::FLOAT => 'float',
            ColumnType::DOUBLE => 'double',
            ColumnType::DECIMAL => 'decimal',
            ColumnType::MONEY => 'decimal',
            ColumnType::CHAR => 'char',
            ColumnType::STRING => 'varchar',
            ColumnType::TEXT => 'text',
            ColumnType::BINARY => 'blob',
            ColumnType::UUID => 'blob(16)',
            ColumnType::DATETIME => 'datetime',
            ColumnType::TIMESTAMP => 'timestamp',
            ColumnType::DATE => 'date',
            ColumnType::TIME => 'time',
            ColumnType::ARRAY => 'json',
            ColumnType::STRUCTURED => 'json',
            ColumnType::JSON => 'json',
            default => 'varchar',
        };
    }
}
