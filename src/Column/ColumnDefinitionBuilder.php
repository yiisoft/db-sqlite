<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\QueryBuilder\AbstractColumnDefinitionBuilder;
use Yiisoft\Db\Schema\Column\ColumnSchemaInterface;

final class ColumnDefinitionBuilder extends AbstractColumnDefinitionBuilder
{
    protected const AUTO_INCREMENT_KEYWORD = 'AUTOINCREMENT';

    protected const GENERATE_UUID_EXPRESSION =
        "(unhex(format('%016X', random() & 0xFFFFFFFFFFFF4FFF | 0x4000) || format('%016X', random() & 0xBFFFFFFFFFFFFFFF | 0xB000000000000000)))";

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

    public function build(ColumnSchemaInterface $column): string
    {
        return $this->buildType($column)
            . $this->buildPrimaryKey($column)
            . $this->buildAutoIncrement($column)
            . $this->buildUnique($column)
            . $this->buildNotNull($column)
            . $this->buildDefault($column)
            . $this->buildCheck($column)
            . $this->buildReferences($column)
            . $this->buildExtra($column)
            . $this->buildComment($column);
    }

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
        return $column->getDbType() ?? match ($column->getType()) {
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
            ColumnType::STRING => 'varchar(' . ($column->getSize() ?? 255) . ')',
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
