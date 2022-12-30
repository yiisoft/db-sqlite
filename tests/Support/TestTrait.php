<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Db\Sqlite\PDODriver;
use Yiisoft\Db\Tests\Support\DbHelper;

trait TestTrait
{
    private string $dsn = '';

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function getConnection(bool $fixture = false): ConnectionPDOInterface
    {
        $db = new ConnectionPDO(
            new PDODriver($this->getDsn()),
            DbHelper::getQueryCache(),
            DbHelper::getSchemaCache(),
        );

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
