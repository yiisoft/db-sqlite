<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Schema\TableSchema as AbstractTableSchema;

final class TableSchema extends AbstractTableSchema
{
    public function compositeFK(int $id, string $from, string $to): void
    {
        $this->foreignKeys[$id][$from] = $to;
    }

    public function foreignKey(int $id, array $to): void
    {
        $this->foreignKeys[$id] = $to;
    }
}
