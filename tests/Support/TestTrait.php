<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Driver\Pdo\PdoConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Db\Tests\Support\DbHelper;

trait TestTrait
{
    private string $dsn = '';

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function getConnection(bool $fixture = false): PdoConnectionInterface
    {
        $db = new Connection(new Driver($this->getDsn()), DbHelper::getSchemaCache());

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

    protected function getDriverName(): string
    {
        return 'sqlite';
    }

    protected function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }
}
