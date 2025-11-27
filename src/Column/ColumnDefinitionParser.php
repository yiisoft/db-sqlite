<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Column;

use Yiisoft\Db\Syntax\AbstractColumnDefinitionParser;

final class ColumnDefinitionParser extends AbstractColumnDefinitionParser
{
    protected function parseTypeParams(string $type, string $params): array
    {
        return match ($type) {
            'bit',
            'char',
            'datetime',
            'datetimetz',
            'decimal',
            'double',
            'float',
            'int',
            'numeric',
            'real',
            'smallint',
            'string',
            'time',
            'timestamp',
            'timetz',
            'tinyint',
            'varchar' => $this->parseSizeInfo($params),
            default => [],
        };
    }
}
