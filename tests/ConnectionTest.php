<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Yiisoft\Cache\CacheKeyNormalizer;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\TestUtility\TestConnectionTrait;
use Yiisoft\Db\Transaction\Transaction;

/**
 * @group sqlite
 */
final class ConnectionTest extends TestCase
{
    use TestConnectionTrait;

    public function testConnection(): void
    {
        $this->assertIsObject($this->getConnection(true));
    }

    public function testConstruct(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('sqlite:' . __DIR__ . '/Runtime/yiitest.sq3', $db->getDsn());
    }

    public function testExceptionContainsRawQuery(): void
    {
        $db = $this->getConnection();

        if ($db->getTableSchema('qlog1', true) === null) {
            $db->createCommand()->createTable('qlog1', ['id' => 'pk'])->execute();
        }

        $db->setEmulatePrepare(true);

        /* profiling and logging */
        $db->setLogger($this->logger);
        $db->setProfiler($this->profiler);

        $this->runExceptionTest($db);

        /* profiling only */
        $db->setLogger(null);
        $db->setProfiler($this->profiler);

        $this->runExceptionTest($db);

        /* logging only */
        $db->setLogger($this->logger);
        $db->setProfiler(null);

        $this->runExceptionTest($db);

        /* disabled */
        $db->setLogger(null);
        $db->setProfiler(null);

        $this->runExceptionTest($db);
    }

    /**
     * @param ConnectionInterface $db
     */
    private function runExceptionTest(ConnectionInterface $db): void
    {
        $thrown = false;

        try {
            $db->createCommand('INSERT INTO qlog1(a) VALUES(:a);', [':a' => 1])->execute();
        } catch (Exception $e) {
            $this->assertStringContainsString(
                'INSERT INTO qlog1(a) VALUES(:a);',
                $e->getMessage(),
                'Exceptions message should contain raw SQL query: ' . (string) $e
            );

            $thrown = true;
        }

        $this->assertTrue($thrown, 'An exception should have been thrown by the command.');

        $thrown = false;

        try {
            $db->createCommand(
                'SELECT * FROM qlog1 WHERE id=:a ORDER BY nonexistingcolumn;',
                [':a' => 1]
            )->queryAll();
        } catch (Exception $e) {
            $this->assertStringContainsString(
                'SELECT * FROM qlog1 WHERE id=:a ORDER BY nonexistingcolumn;',
                $e->getMessage(),
                'Exceptions message should contain raw SQL query: ' . (string) $e
            );

            $thrown = true;
        }

        $this->assertTrue($thrown, 'An exception should have been thrown by the command.');
    }

    public function testGetDriverName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('sqlite', $db->getDriverName());
    }

    /**
     * Test whether slave connection is recovered when call `getSlavePdo()` after `close()`.
     *
     * {@see https://github.com/yiisoft/yii2/issues/14165}
     */
    public function testGetPdoAfterClose(): void
    {
        $db = $this->getConnection();

        $db->setSlave(
            '1',
            $this->createConnection('sqlite:' . __DIR__ . '/Runtime/yii_test_slave.sq3'),
        );

        $this->assertNotNull($db->getSlavePdo(false));

        $db->close();

        $masterPdo = $db->getMasterPdo();

        $this->assertNotFalse($masterPdo);
        $this->assertNotNull($masterPdo);

        $slavePdo = $db->getSlavePdo(false);

        $this->assertNotFalse($slavePdo);
        $this->assertNotNull($slavePdo);
        $this->assertNotSame($masterPdo, $slavePdo);
    }

    public function testMasterSlave(): void
    {
        $counts = [[0, 2], [1, 2], [2, 2]];

        foreach ($counts as $count) {
            [$masterCount, $slaveCount] = $count;

            $db = $this->prepareMasterSlave($masterCount, $slaveCount);

            $this->assertInstanceOf(Connection::class, $db->getSlave());
            $this->assertTrue($db->getSlave()->isActive());
            $this->assertFalse($db->isActive());

            /* test SELECT uses slave */
            $this->assertEquals(2, $db->createCommand('SELECT COUNT(*) FROM profile')->queryScalar());
            $this->assertFalse($db->isActive());

            /* test UPDATE uses master */
            $db->createCommand("UPDATE profile SET description='test' WHERE id=1")->execute();
            $this->assertTrue($db->isActive());

            if ($masterCount > 0) {
                $this->assertInstanceOf(Connection::class, $db->getMaster());
                $this->assertTrue($db->getMaster()->isActive());
            } else {
                $this->assertNull($db->getMaster());
            }

            $this->assertNotEquals(
                'test',
                $db->createCommand('SELECT description FROM profile WHERE id=1')->queryScalar()
            );

            $result = $db->useMaster(static function (Connection $db) {
                return $db->createCommand('SELECT description FROM profile WHERE id=1')->queryScalar();
            });

            $this->assertEquals('test', $result);
        }
    }

    public function testMastersShuffled(): void
    {
        $mastersCount = 2;
        $slavesCount = 2;
        $retryPerNode = 10;

        $nodesCount = $mastersCount + $slavesCount;

        $hit_slaves = $hit_masters = [];

        for ($i = $nodesCount * $retryPerNode; $i-- > 0;) {
            $db = $this->prepareMasterSlave($mastersCount, $slavesCount);
            $db->setShuffleMasters(true);

            $hit_slaves[$db->getSlave()->getDsn()] = true;
            $hit_masters[$db->getMaster()->getDsn()] = true;

            if (\count($hit_slaves) === $slavesCount && \count($hit_masters) === $mastersCount) {
                break;
            }
        }

        $this->assertCount($mastersCount, $hit_masters, 'all masters hit');
        $this->assertCount($slavesCount, $hit_slaves, 'all slaves hit');
    }

    public function testMastersSequential(): void
    {
        $mastersCount = 2;
        $slavesCount = 2;
        $retryPerNode = 10;

        $nodesCount = $mastersCount + $slavesCount;

        $hit_slaves = $hit_masters = [];

        for ($i = $nodesCount * $retryPerNode; $i-- > 0;) {
            $db = $this->prepareMasterSlave($mastersCount, $slavesCount);
            $db->setShuffleMasters(false);

            $hit_slaves[$db->getSlave()->getDsn()] = true;
            $hit_masters[$db->getMaster()->getDsn()] = true;

            if (\count($hit_slaves) === $slavesCount) {
                break;
            }
        }

        $this->assertCount(1, $hit_masters, 'same master hit');

        /* slaves are always random */
        $this->assertCount($slavesCount, $hit_slaves, 'all slaves hit');
    }

    public function testOpenClose(): void
    {
        $db = $this->getConnection();

        $this->assertFalse($db->isActive());
        $this->assertNull($db->getPDO());

        $db->open();

        $this->assertTrue($db->isActive());
        $this->assertInstanceOf(PDO::class, $db->getPDO());

        $db->close();

        $this->assertFalse($db->isActive());
        $this->assertNull($db->getPDO());

        $db = $this->createConnection('unknown::memory:');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not find driver');

        $db->open();
    }

    public function testQuoteValue(): void
    {
        $connection = $this->getConnection();

        $this->assertEquals(123, $connection->quoteValue(123));
        $this->assertEquals("'string'", $connection->quoteValue('string'));
        $this->assertEquals("'It''s interesting'", $connection->quoteValue("It's interesting"));
    }

    public function testRestoreMasterAfterException(): void
    {
        $db = $this->prepareMasterSlave(1, 1);
        $this->assertTrue($db->areSlavesEnabled());

        try {
            $db->useMaster(static function (Connection $db) {
                throw new Exception('fail');
            });
            $this->fail('Exceptions was caught somewhere');
        } catch (Exception $e) {
            /* ok */
        }

        $this->assertTrue($db->areSlavesEnabled());
    }

    public function testServerStatusCacheWorks(): void
    {
        $cacheKeyNormalizer = new CacheKeyNormalizer();

        $db = $this->getConnection(true);

        $db->setMaster(
            '1',
            $this->createConnection('sqlite:' . __DIR__ . '/Runtime/yii_test_master.sq3'),
        );

        $db->setShuffleMasters(false);

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDsn()]
        );

        $this->assertFalse($this->cache->psr()->has($cacheKey));

        $db->open();

        $this->assertFalse(
            $this->cache->psr()->has($cacheKey),
            'Connection was successful – cache must not contain information about this DSN'
        );

        $db->close();

        $db = $this->getConnection();

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid']
        );

        $db->setMaster(
            '1',
            $this->createConnection('host:invalid'),
        );

        $db->setShuffleMasters(true);

        try {
            $db->open();
        } catch (InvalidConfigException $e) {
        }

        $this->assertTrue(
            $this->cache->psr()->has($cacheKey),
            'Connection was not successful – cache must contain information about this DSN'
        );

        $db->close();
    }

    public function testServerStatusCacheCanBeDisabled(): void
    {
        $cacheKeyNormalizer = new CacheKeyNormalizer();

        $this->cache->psr()->clear();

        $db = $this->getConnection();

        $db->setMaster(
            '1',
            $this->createConnection('sqlite:' . __DIR__ . '/Runtime/yii_test_master.sq3'),
        );

        $this->schemaCache->setEnable(false);

        $db->setShuffleMasters(false);

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDsn()]
        );

        $this->assertFalse($this->cache->psr()->has($cacheKey));

        $db->open();

        $this->assertFalse($this->cache->psr()->has($cacheKey), 'Caching is disabled');

        $db->close();

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid']
        );

        $db->setMaster(
            '1',
            $this->createConnection('host:invalid'),
        );

        try {
            $db->open();
        } catch (InvalidConfigException $e) {
        }

        $this->assertFalse($this->cache->psr()->has($cacheKey), 'Caching is disabled');

        $db->close();
    }

    public function testTransactionIsolation(): void
    {
        $connection = $this->getConnection(true);

        $transaction = $connection->beginTransaction(Transaction::READ_UNCOMMITTED);

        $transaction->rollBack();

        $transaction = $connection->beginTransaction(Transaction::SERIALIZABLE);

        $transaction->rollBack();

        /* No exceptions means test is passed. */
        $this->assertTrue(true);
    }

    protected function prepareMasterSlave($masterCount, $slaveCount): Connection
    {
        $db = $this->getConnection(true);

        for ($i = 0; $i < $masterCount; ++$i) {
            $this->prepareDatabase('sqlite:' . __DIR__ . "/Runtime/yii_test_master{$i}.sq3");

            $db->setMaster(
                "$i",
                $this->createConnection('sqlite:' . __DIR__ . "/Runtime/yii_test_master{$i}.sq3"),
            );
        }

        for ($i = 0; $i < $slaveCount; ++$i) {
            $this->prepareDatabase('sqlite:' . __DIR__ . "/Runtime/yii_test_slave{$i}.sq3");

            $db->setSlave(
                "$i",
                $this->createConnection('sqlite:' . __DIR__ . "/Runtime/yii_test_slave{$i}.sq3"),
            );
        }

        $db->close();

        return $db;
    }
}
