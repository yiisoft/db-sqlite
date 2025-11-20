<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Db\Tests\Support\TestHelper;

final class TestConnection
{
    private static ?Connection $connection = null;

    public static function getShared(): Connection
    {
        $db = self::$connection ??= self::create();
        $db->getSchema()->refresh();
        return $db;
    }

    public static function getServerVersion(): string
    {
        return self::getShared()->getServerInfo()->getVersion();
    }

    public static function create(?string $dsn = null): Connection
    {
        return new Connection(self::createDriver($dsn), TestHelper::createMemorySchemaCache());
    }

    public static function createDriver(?string $dsn = null): Driver
    {
        $dsn = new Dsn(
            databaseName: 'memory',
        );

        $driver = new Driver($dsn);
        $driver->charset('utf8');

        return $driver;
    }
}
