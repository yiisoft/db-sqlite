<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionObject;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Factory\DatabaseFactory;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Tests\TestSupport\TestTrait;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Profiler\ProfilerInterface;

use function explode;
use function file_get_contents;
use function str_replace;
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
    protected ContainerInterface $container;
    protected LoggerInterface $logger;
    protected ProfilerInterface $profiler;
    protected QueryCache $queryCache;
    protected SchemaCache $schemaCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getConnection()->close();

        unset(
            $this->aliases,
            $this->cache,
            $this->connection,
            $this->container,
            $this->dataProvider,
            $this->logger,
            $this->queryCache,
            $this->schemaCache,
            $this->profiler
        );
    }

    protected function configContainer(): void
    {
        $this->container = new Container($this->config());

        $this->aliases = $this->container->get(Aliases::class);
        $this->cache = $this->container->get(CacheInterface::class);
        $this->connection = $this->container->get(ConnectionInterface::class);
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->profiler = $this->container->get(ProfilerInterface::class);
        $this->queryCache = $this->container->get(QueryCache::class);
        $this->schemaCache = $this->container->get(SchemaCache::class);

        DatabaseFactory::initialize($this->container, []);
    }

    /**
     * @param bool $reset whether to clean up the test database.
     *
     * @return Connection
     */
    protected function getConnection($reset = false): Connection
    {
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

    private function config(): array
    {
        $params = $this->params();

        return [
            Aliases::class => [
                '__construct()' => [
                    [
                        '@root' => dirname(__DIR__, 1),
                        '@data' => '@root/tests/Data',
                        '@runtime' => '@data/runtime',
                    ],
                ],
            ],

            CacheInterface::class => [
                'class' => Cache::class,
                '__construct()' => [
                    Reference::to(ArrayCache::class),
                ],
            ],

            LoggerInterface::class => Logger::class,

            ProfilerInterface::class => Profiler::class,

            ConnectionInterface::class => [
                'class' => Connection::class,
                '__construct()' => [
                    'dsn' => $params['yiisoft/db-sqlite']['dsn'],
                ],
                'setLogger()' => [DynamicReference::to(LoggerInterface::class)],
                'setProfiler()' => [DynamicReference::to(ProfilerInterface::class)],
            ],
        ];
    }

    protected function createConnection(string $dsn = null): ?Connection
    {
        $db = null;

        if ($dsn !== null) {
            $this->configContainer();
            $db = DatabaseFactory::connection(['class' => Connection::class, '__construct()' => ['dsn' => $dsn]]);
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
