<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Exception;
use PHPUnit\Framework\TestCase as AbstractTestCase;
use Yiisoft\Db\Driver\PDODriver;
use Yiisoft\Db\Sqlite\PDO\ConnectionPDOSqlite;
use Yiisoft\Db\TestSupport\TestTrait;

class TestCase extends AbstractTestCase
{
    use TestTrait;

    protected string $drivername = 'sqlite';
    protected string $username = '';
    protected string $password = '';
    protected string $charset = 'UTF8';
    protected array $dataProvider;
    protected string $likeEscapeCharSql = '';
    protected array $likeParameterReplacements = [];
    protected ConnectionPDOSqlite $db;

    /**
     * @param bool $reset whether to clean up the test database.
     *
     * @return ConnectionPDOSqlite
     */
    protected function getConnection(
        $reset = false,
        string $dsn = 'sqlite:' . __DIR__ . '/Runtime/yiitest.sq3',
        string $fixture = __DIR__ . '/Fixture/sqlite.sql'
    ): ConnectionPDOSqlite {
        $pdoDriver = new PDODriver($dsn, $this->username, $this->password);
        $this->db = new ConnectionPDOSqlite($pdoDriver, $this->createQueryCache(), $this->createSchemaCache());
        $this->db->setLogger($this->createLogger());
        $this->db->setProfiler($this->createProfiler());

        if ($reset === false) {
            return $this->db;
        }

        try {
            $this->prepareDatabase($this->db, $fixture);
        } catch (Exception $e) {
            $this->markTestSkipped('Something wrong when preparing database: ' . $e->getMessage());
        }

        return $this->db;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset(
            $this->cache,
            $this->db,
            $this->logger,
            $this->queryCache,
            $this->schemaCache,
            $this->profiler
        );
    }
}
