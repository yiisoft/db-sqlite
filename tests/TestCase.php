<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\TestUtility\TestTrait;

use function explode;
use function file_get_contents;
use function trim;

class TestCase extends AbstractTestCase
{
    use TestTrait;

    protected const DB_DSN = 'sqlite:' . __DIR__ . '/Runtime/yiitest.sq3';
    protected const DB_FIXTURES_PATH = __DIR__ . '/Fixture/sqlite.sql';
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

    protected function createConnection(string $dsn = null): ?ConnectionInterface
    {
        $db = null;

        if ($dsn !== null) {
            $db = new Connection($dsn, $this->createQueryCache(), $this->createSchemaCache());
            $db->setLogger($this->createLogger());
            $db->setProfiler($this->createProfiler());
        }

        return $db;
    }
}
