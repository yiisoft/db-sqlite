<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\Sqlite\PDODriver;
use Yiisoft\Db\Tests\Support\DbHelper;

trait TestTrait
{
    protected function getConnection(string ...$fixtures): ConnectionPDOInterface
    {
        $db = new ConnectionPDO(new PDODriver('sqlite::memory:'), DbHelper::getQueryCache(), DbHelper::getSchemaCache());

        foreach ($fixtures as $fixture) {
            DbHelper::loadFixture($db, __DIR__ . "/Fixture/$fixture.sql");
        }

        return $db;
    }
}
