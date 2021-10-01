<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\TestUtility\TestTrait;

class TestCase extends AbstractTestCase
{
    use TestTrait;

    protected const DB_CONNECTION_CLASS = \Yiisoft\Db\Sqlite\Connection::class;
    protected const DB_DRIVERNAME = 'sqlite';
    protected const DB_DSN = 'sqlite:' . __DIR__ . '/Runtime/yiitest.sq3';
    protected const DB_FIXTURES_PATH = __DIR__ . '/Fixture/sqlite.sql';
    protected const DB_USERNAME = '';
    protected const DB_PASSWORD = '';
    protected const DB_CHARSET = 'UTF8';
    protected array $dataProvider;
    protected string $likeEscapeCharSql = '';
    protected array $likeParameterReplacements = [];
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createConnection(self::DB_DSN);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->close();
        unset(
            $this->cache,
            $this->connection,
            $this->logger,
            $this->queryCache,
            $this->schemaCache,
            $this->profiler
        );
    }
}
