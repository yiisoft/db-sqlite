<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Tests\TestSupport\TestTrait;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Profiler\ProfilerInterface;
use Yiisoft\Test\Support\Container\SimpleContainer;

use function explode;
use function file_get_contents;
use function trim;

class TestCase extends AbstractTestCase
{
    use TestTrait;

    protected array $dataProvider;
    protected string $likeEscapeCharSql = '';
    protected array $likeParameterReplacements = [];
    protected Aliases $aliases;
    protected CacheInterface $cache;
    protected Connection $connection;
    protected LoggerInterface $logger;
    protected ProfilerInterface $profiler;
    protected QueryCache $queryCache;
    protected SchemaCache $schemaCache;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->createContainer();

        $this->aliases = $container->get(Aliases::class);
        $this->cache = $container->get(CacheInterface::class);
        $this->connection = $container->get(ConnectionInterface::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->profiler = $container->get(ProfilerInterface::class);
        $this->queryCache = $container->get(QueryCache::class);
        $this->schemaCache = $container->get(SchemaCache::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getConnection()->close();

        unset(
            $this->aliases,
            $this->cache,
            $this->connection,
            $this->dataProvider,
            $this->logger,
            $this->queryCache,
            $this->schemaCache,
            $this->profiler
        );
    }

    /**
     * @param bool $reset whether to clean up the test database.
     *
     * @return Connection
     */
    protected function getConnection($reset = false): Connection
    {
        if ($reset === false && isset($this->connection)) {
            return $this->connection;
        }

        if ($reset === false) {
            return $this->createConnection($this->params()['yiisoft/db-sqlite']['dsn']);
        }

        try {
            $this->prepareDatabase();
        } catch (Exception $e) {
            $this->markTestSkipped('Something wrong when preparing database: ' . $e->getMessage());
        }

        return $this->connection;
    }

    protected function prepareDatabase(string $dsn = null): void
    {
        $fixture = $this->params()['yiisoft/db-sqlite']['fixture'];

        if ($dsn !== null) {
            $this->connection = $this->createConnection($dsn);
        }

        $this->connection->open();

        if ($fixture !== null) {
            $lines = explode(';', file_get_contents($this->aliases->get($fixture)));

            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $this->connection->getPDO()->exec($line);
                }
            }
        }
    }

    private function createContainer(): ContainerInterface
    {
        $aliases = new Aliases(
            ['@root' => dirname(__DIR__, 1), '@data' => '@root/tests/Data', '@runtime' => '@data/runtime']
        );
        $cache = new Cache(new ArrayCache());
        $logger = new Logger();
        $profiler = new Profiler($logger);
        $queryCache = new QueryCache($cache);
        $schemaCache = new SchemaCache($cache);
        $connection = new Connection($this->params()['yiisoft/db-sqlite']['dsn'], $queryCache, $schemaCache);
        $connection->setLogger($logger);
        $connection->setProfiler($profiler);

        $simpleContainer = new SimpleContainer(
            [
                Aliases::class => $aliases,
                CacheInterface::class => $cache,
                LoggerInterface::class => $logger,
                ProfilerInterface::class => $profiler,
                QueryCache::class => $queryCache,
                SchemaCache::class => $schemaCache,
                ConnectionInterface::class => $connection,
            ]
        );

        return $simpleContainer;
    }

    protected function createConnection(string $dsn = null): ?Connection
    {
        $db = null;

        if ($dsn !== null) {
            $cache = new Cache(new ArrayCache());
            $db = new Connection($dsn, new QueryCache($cache), new SchemaCache($cache));
        }

        return $db;
    }

    protected function params(): array
    {
        return [
            'yiisoft/db-sqlite' => [
                'dsn' => 'sqlite:' . __DIR__ . '/Data/yiitest.sq3',
                'fixture' => __DIR__ . '/Data/sqlite.sql',
            ],
        ];
    }
}
