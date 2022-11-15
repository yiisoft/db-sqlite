<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Exception;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Provider\BaseCommandProvider;

final class CommandProvider
{
    use TestTrait;

    /**
     * @throws Exception
     */
    public function batchInsertSql(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        $batchInsertSql = $baseCommandProvider->batchInsertSql($this->getConnection());
        unset($batchInsertSql['wrongBehavior']);

        return $batchInsertSql;
    }

    /**
     * @throws Exception
     */
    public function upsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->upsert($this->getConnection());
    }
}
