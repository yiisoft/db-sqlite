<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\Sqlite\PDODriver;
use Yiisoft\Db\Tests\Support\DbHelper;

trait TestTrait
{
    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function getConnection(bool $fixture = false): ConnectionPDOInterface
    {
        $db = new ConnectionPDO(
            new PDODriver('sqlite::memory:'),
            DbHelper::getQueryCache(),
            DbHelper::getSchemaCache(),
        );

        if ($fixture) {
            DbHelper::loadFixture($db, __DIR__ . "/Fixture/sqlite.sql");
        }

        return $db;
    }
}
