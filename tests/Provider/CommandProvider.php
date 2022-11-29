<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Provider\BaseCommandProvider;

final class CommandProvider
{
    use TestTrait;

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function batchInsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        $batchInsertSql = $baseCommandProvider->batchInsert($this->getConnection());
        unset($batchInsertSql['wrongBehavior']);

        return $batchInsertSql;
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function rawSql(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->rawSql($this->getConnection());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function update(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->update($this->getConnection());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function upsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->upsert($this->getConnection());
    }
}
