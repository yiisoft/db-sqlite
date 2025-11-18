<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Db\Tests\Support\TestHelper;

trait IntegrationTestTrait
{
    protected function createConnection(): Connection
    {
        return new Connection(
            $this->createDriver(),
            TestHelper::createMemorySchemaCache(),
        );
    }

    protected function createDriver(): Driver
    {
        $dsn = new Dsn(
            databaseName: 'memory',
        );

        $driver = new Driver($dsn);
        $driver->charset('utf8');

        return $driver;
    }

    protected function getDefaultFixture(): string
    {
        return __DIR__ . '/Fixture/sqlite.sql';
    }

    protected function replaceQuotes(string $sql): string
    {
        return str_replace(['[[', ']]'], '"', $sql);
    }
}
