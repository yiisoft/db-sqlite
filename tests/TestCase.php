<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Factory\DatabaseFactory;
use Yiisoft\Db\Helper\Dsn;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\TestUtility\IsOneOfAssert;
use Yiisoft\Di\Container;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;

use function explode;
use function file_get_contents;
use function str_replace;
use function trim;

class TestCase extends AbstractTestCase
{
    protected Aliases $aliases;
    protected CacheInterface $cache;
    protected Connection $connection;
    protected ContainerInterface $container;
    protected array $dataProvider;
    protected string $likeEscapeCharSql = '';
    protected array $likeParameterReplacements = [];
    protected LoggerInterface $logger;
    protected Profiler $profiler;

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
            $this->profiler
        );
    }

    protected function buildKeyCache(array $key): string
    {
        $jsonKey = json_encode($key);

        return md5($jsonKey);
    }

    /**
     * Asserting two strings equality ignoring line endings.
     *
     * @param string $expected
     * @param string $actual
     * @param string $message
     *
     * @return void
     */
    protected function assertEqualsWithoutLE(string $expected, string $actual, string $message = ''): void
    {
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts that value is one of expected values.
     *
     * @param mixed $actual
     * @param array $expected
     * @param string $message
     */
    protected function assertIsOneOf($actual, array $expected, $message = ''): void
    {
        self::assertThat($actual, new IsOneOfAssert($expected), $message);
    }

    protected function configContainer(): void
    {
        $this->container = new Container($this->config());

        $this->aliases = $this->container->get(Aliases::class);
        $this->cache = $this->container->get(CacheInterface::class);
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->profiler = $this->container->get(Profiler::class);
        $this->connection = $this->container->get(ConnectionInterface::class);

        DatabaseFactory::initialize($this->container, []);
    }

    /**
     * Invokes a inaccessible method.
     *
     * @param object $object
     * @param string $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution.
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    protected function invokeMethod(object $object, string $method, array $args = [], bool $revoke = true)
    {
        $reflection = new ReflectionObject($object);

        $method = $reflection->getMethod($method);

        $method->setAccessible(true);

        $result = $method->invokeArgs($object, $args);

        if ($revoke) {
            $method->setAccessible(false);
        }
        return $result;
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
            $this->configContainer();
            return $this->connection;
        }

        try {
            $this->prepareDatabase();
        } catch (Exception $e) {
            $this->markTestSkipped('Something wrong when preparing database: ' . $e->getMessage());
        }

        return $this->connection;
    }

    protected function prepareDatabase(?string $fixture = null, ?string $dsn = null): void
    {
        if ($fixture === null) {
            $fixture = $this->params()['yiisoft/db-sqlite']['fixture'];
        }

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

    /**
     * Gets an inaccessible object property.
     *
     * @param object $object
     * @param string $propertyName
     * @param bool $revoke whether to make property inaccessible after getting.
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    protected function getInaccessibleProperty(object $object, string $propertyName, bool $revoke = true)
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);

        $property->setAccessible(true);

        $result = $property->getValue($object);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $result;
    }

    /**
     * Adjust dbms specific escaping.
     *
     * @param string|array $sql
     *
     * @return string
     */
    protected function replaceQuotes($sql): string
    {
        return str_replace(['[[', ']]'], '`', $sql);
    }

    /**
     * Sets an inaccessible object property to a designated value.
     *
     * @param object $object
     * @param string $propertyName
     * @param $value
     * @param bool $revoke whether to make property inaccessible after setting
     *
     * @throws ReflectionException
     */
    protected function setInaccessibleProperty(object $object, string $propertyName, $value, bool $revoke = true): void
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);

        $property->setAccessible(true);

        $property->setValue($object, $value);

        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    private function config(): array
    {
        $params = $this->params();

        return [
            Aliases::class => [
                '@root' => dirname(__DIR__, 1),
                '@data' =>  '@root/tests/Data',
                '@runtime' => '@data/runtime',
            ],

            CacheInterface::class => static function () {
                return new Cache(new ArrayCache());
            },

            LoggerInterface::class => Logger::class,

            Profiler::class => static function (ContainerInterface $container) {
                return new Profiler($container->get(LoggerInterface::class));
            },

            ConnectionInterface::class  => static function (ContainerInterface $container) use ($params) {
                $connection = new Connection(
                    $container->get(CacheInterface::class),
                    $container->get(LoggerInterface::class),
                    $container->get(Profiler::class),
                    $params['yiisoft/db-sqlite']['dsn'],
                );

                return $connection;
            }
        ];
    }

    protected function createConnection(?string $dsn = null): ?Connection
    {
        $db = null;

        if ($dsn !== null) {
            $this->configContainer();

            $db = new Connection($this->cache, $this->logger, $this->profiler, $dsn);
        }

        return $db;
    }

    protected function params(): array
    {
        return [
            'yiisoft/db-sqlite' => [
                'dsn' => 'sqlite:' . __DIR__ . '/Data/yiitest.sq3',
                'fixture' => __DIR__ . '/Data/sqlite.sql'
            ]
        ];
    }
}
