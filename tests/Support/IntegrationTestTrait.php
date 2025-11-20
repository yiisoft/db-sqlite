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
        return TestConnection::create();
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
