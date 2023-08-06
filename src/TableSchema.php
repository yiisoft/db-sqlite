<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Schema\AbstractTableSchema;

/**
 * Implements the SQLite Server specific table schema.
 */
final class TableSchema extends AbstractTableSchema
{
    /**
     * @deprecated will be removed in version 2.0.0
     */
    public function compositeForeignKey(int $id, string $from, string $to): void
    {
        $this->foreignKeys[$id][$from] = $to;
    }
}
