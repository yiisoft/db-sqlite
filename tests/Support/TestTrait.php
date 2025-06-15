<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Driver\Pdo\PdoDriverInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Db\Tests\Support\DbHelper;

trait TestTrait
{
    private string $dsn = '';

    protected function getConnection(bool $fixture = false): Connection
    {
        $db = new Connection($this->getDriver(), DbHelper::getSchemaCache());

        if ($fixture) {
            DbHelper::loadFixture($db, __DIR__ . '/Fixture/sqlite.sql');
        }

        return $db;
    }

    protected function getDsn(): string
    {
        if ($this->dsn === '') {
            $this->dsn = (new Dsn('sqlite', 'memory'))->asString();
        }

        return $this->dsn;
    }

    protected function getDriver(): PdoDriverInterface
    {
        return new Driver($this->getDsn());
    }

    protected static function getDriverName(): string
    {
        return 'sqlite';
    }

    protected function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }
}
