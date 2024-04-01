<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Expression\AbstractExpressionBuilder;
use Yiisoft\Db\Sqlite\SqlParser;

final class ExpressionBuilder extends AbstractExpressionBuilder
{
    protected function createSqlParser(string $sql): SqlParser
    {
        return new SqlParser($sql);
    }
}
