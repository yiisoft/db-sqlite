<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Exception;
use PHPUnit\Framework\TestCase as AbstractTestCase;
use Yiisoft\Db\Sqlite\PDODriver;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\TestSupport\TestTrait;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class TestCase extends AbstractTestCase
{
    use TestTrait;

    protected string $drivername = 'sqlite';
    protected string $dsn;
    protected string $username = '';
    protected string $password = '';
    protected string $charset = 'UTF8';
    protected array $dataProvider;
    protected string $likeEscapeCharSql = '';
    protected array $likeParameterReplacements = [];
    protected ConnectionPDO $db;

    protected function getConnection(
        bool $reset = false,
        string $dsn = 'sqlite:' . __DIR__ . '/Support/Runtime/yiitest.sq3',
        string $fixture = __DIR__ . '/Support/Fixture/sqlite.sql'
    ): ConnectionPDO {
        $this->dsn = $dsn;
        $pdoDriver = new PDODriver($this->dsn, $this->username, $this->password);
        $this->db = new ConnectionPDO($pdoDriver, $this->createQueryCache(), $this->createSchemaCache());
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
