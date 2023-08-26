<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use JsonException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Schema\AbstractColumnSchema;
use Yiisoft\Db\Schema\SchemaInterface;

use function json_decode;

/**
 * Represents the metadata of a column in a database table for SQLite Server.
 *
 * It provides information about the column's name, type, size, precision, and other details.
 *
 * It's used to store and retrieve metadata about a column in a database table and is typically used in conjunction with
 * the {@see TableSchema}, which represents the metadata of a database table as a whole.
 *
 * The following code shows how to use:
 *
 * ```php
 * use Yiisoft\Db\Sqlite\ColumnSchema;
 *
 * $column = new ColumnSchema();
 * $column->name('id');
 * $column->allowNull(false);
 * $column->dbType('integer');
 * $column->phpType('integer');
 * $column->type('integer');
 * $column->defaultValue(0);
 * $column->autoIncrement(true);
 * $column->primaryKey(true);
 * ```
 */
final class ColumnSchema extends AbstractColumnSchema
{
    /**
     * Converts a value from its PHP representation to a database-specific representation.
     *
     * If the value is null or an {@see Expression}, it won't be converted.
     *
     * @param mixed $value The value to be converted.
     *
     * @return mixed The converted value.
     */
    public function dbTypecast(mixed $value): mixed
    {
        if ($value === null || $value instanceof ExpressionInterface) {
            return $value;
        }

        if ($this->getType() === SchemaInterface::TYPE_JSON) {
            return new JsonExpression($value, $this->getDbType());
        }

        return parent::dbTypecast($value);
    }

    /**
     * Converts the input value according to {@see phpType} after retrieval from the database.
     *
     * If the value is null or an {@see Expression}, it won't be converted.
     *
     * @param mixed $value The value to be converted.
     *
     * @throws JsonException
     * @return mixed The converted value.
     */
    public function phpTypecast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->getType() === SchemaInterface::TYPE_JSON) {
            return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        }

        return parent::phpTypecast($value);
    }
}
