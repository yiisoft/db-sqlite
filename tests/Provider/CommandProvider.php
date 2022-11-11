<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Exception;
use Yiisoft\Db\Sqlite\Tests\Support\SqliteConnection;
use Yiisoft\Db\Tests\Provider\BaseCommandProvider;

final class CommandProvider
{
    /**
     * @throws Exception
     */
    public function batchInsertSql(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        $batchInsertSql = $baseCommandProvider->batchInsertSql(SqliteConnection::getConnection());
        unset($batchInsertSql['wrongBehavior']);

        return $batchInsertSql;
    }

    /**
     * @throws Exception
     */
    public function upsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->upsert(SqliteConnection::getConnection());
    }
}
