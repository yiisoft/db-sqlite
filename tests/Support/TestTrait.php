<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Support;

use Psr\Log\LoggerInterface;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Query\Query;
use Yiisoft\Profiler\ProfilerInterface;

trait TestTrait
{
    protected function getCache(): CacheInterface
    {
        return SqliteConnection::getCache();
    }

    /**
     * @throws Exception
     */
    protected function getConnection(): ConnectionPDOInterface
    {
        return SqliteConnection::getConnection();
    }

    /**
     * @throws Exception
     */
    protected function getConnectionWithData(): ConnectionPDOInterface
    {
        return SqliteConnection::getConnection(true);
    }

    /**
     * @throws Exception
     */
    protected function getConnectionWithDsn(string $dsn): ConnectionPDOInterface
    {
        return SqliteConnection::getConnection(false, $dsn);
    }

    protected function getLogger(): LoggerInterface
    {
        return SqliteConnection::getLogger();
    }

    protected function getQuery(ConnectionPDOInterface $db): Query
    {
        return new Query($db);
    }

    protected function getQueryCache(): QueryCache
    {
        return SqliteConnection::getQueryCache();
    }

    protected function getProfiler(): ProfilerInterface
    {
        return SqliteConnection::getProfiler();
    }

    protected function getSchemaCache(): SchemaCache
    {
        return SqliteConnection::getSchemaCache();
    }
}
