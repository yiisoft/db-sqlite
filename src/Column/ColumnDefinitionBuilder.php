<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\AbstractColumnDefinitionBuilder;
use Yiisoft\Db\Schema\Column\ColumnInterface;

final class ColumnDefinitionBuilder extends AbstractColumnDefinitionBuilder
{
    protected const AUTO_INCREMENT_KEYWORD = 'AUTOINCREMENT';

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
        'timestamp',
        'datetime',
        'datetimetz',
        'time',
        'timetz',
    ];

    protected const TYPES_WITH_SCALE = [
        'float',
        'real',
        'double',
        'decimal',
        'numeric',
    ];

    public function build(ColumnInterface $column): string
    {
        if ($column->isUnsigned()) {
            throw new NotSupportedException('The "unsigned" attribute is not supported by SQLite.');
        }

        return $this->buildType($column)
            . $this->buildPrimaryKey($column)
            . $this->buildAutoIncrement($column)
            . $this->buildUnique($column)
            . $this->buildNotNull($column)
            . $this->buildDefault($column)
            . $this->buildCheck($column)
            . $this->buildCollate($column)
            . $this->buildReferences($column)
            . $this->buildExtra($column)
            . $this->buildComment($column);
    }

    protected function buildComment(ColumnInterface $column): string
    {
        $comment = $column->getComment();

        return $comment === null || $comment === '' ? '' : ' /* ' . str_replace('*/', '*\/', $comment) . ' */';
    }

    protected function buildNotNull(ColumnInterface $column): string
    {
        return $column->isPrimaryKey() ? ' NOT NULL' : parent::buildNotNull($column);
    }

    protected function getDbType(ColumnInterface $column): string
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
            ColumnType::TIMESTAMP => 'timestamp',
            ColumnType::DATETIME => 'datetime',
            ColumnType::DATETIMETZ => 'datetimetz',
            ColumnType::TIME => 'time',
            ColumnType::TIMETZ => 'timetz',
            ColumnType::DATE => 'date',
            ColumnType::ARRAY => 'json',
            ColumnType::STRUCTURED => 'json',
            ColumnType::JSON => 'json',
            ColumnType::ENUM => 'varchar',
            default => 'varchar',
        };
    }

    protected function getDefaultUuidExpression(): string
    {
        return '(randomblob(16))';
    }
}
