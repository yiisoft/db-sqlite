<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Psr\Log\NullLogger;
use Yiisoft\Cache\CacheKeyNormalizer;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\TestSupport\TestConnectionTrait;
use Yiisoft\Db\Transaction\TransactionInterface;

use function count;

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
        $db->setLogger(new NullLogger());
        $db->setProfiler($this->profiler);
        $this->runExceptionTest($db);

        /* logging only */
        $db->setLogger($this->logger);
        $db->notProfiler();
        $this->runExceptionTest($db);

        /* disabled */
        $db->setLogger(new NullLogger());
        $db->notProfiler();
        $this->runExceptionTest($db);
    }

    public function testGetDriverName(): void
    {
        $db = $this->getConnection();
        $this->assertEquals('sqlite', $db->getDriver()->getDriverName());
    }

    public function testGetEmulatePrepare(): void
    {
        $db = $this->getConnection();

        $this->assertNull($db->getEmulatePrepare());

        $db->setEmulatePrepare(true);
        $db->open();

        $this->assertTrue($db->getEmulatePrepare());

        $db->close();
    }

    /**
     * Test whether slave connection is recovered when call `getSlavePdo()` after `close()`.
     *
     * {@see https://github.com/yiisoft/yii2/issues/14165}
     */
    public function testGetPdoAfterClose(): void
    {
        $this->markTestSkipped('Only for master/slave');

        $db = $this->getConnection();

        $db->setSlave(
            '1',
            $this->getConnection(false, 'sqlite:' . __DIR__ . '/Runtime/yii_test_slave.sq3'),
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
        $this->markTestSkipped('Only for master/slave');

        $counts = [[0, 2], [1, 2], [2, 2]];

        foreach ($counts as $count) {
            [$masterCount, $slaveCount] = $count;

            $db = $this->prepareMasterSlave($masterCount, $slaveCount);
            $this->assertInstanceOf(ConnectionInterface::class, $db->getSlave());
            $this->assertTrue($db->getSlave()->isActive());
            $this->assertFalse($db->isActive());

            /* test SELECT uses slave */
            $this->assertEquals(2, $db->createCommand('SELECT COUNT(*) FROM profile')->queryScalar());
            $this->assertFalse($db->isActive());

            /* test UPDATE uses master */
            $db->createCommand("UPDATE profile SET description='test' WHERE id=1")->execute();
            $this->assertTrue($db->isActive());

            if ($masterCount > 0) {
                $this->assertInstanceOf(ConnectionInterface::class, $db->getMaster());
                $this->assertTrue($db->getMaster()->isActive());
            } else {
                $this->assertNull($db->getMaster());
            }

            $this->assertNotEquals(
                'test',
                $db->createCommand('SELECT description FROM profile WHERE id=1')->queryScalar()
            );

            $result = $db->useMaster(static fn (ConnectionInterface $db) => $db->createCommand('SELECT description FROM profile WHERE id=1')->queryScalar());
            $this->assertEquals('test', $result);
        }
    }

    public function testMastersShuffled(): void
    {
        $mastersCount = null;
        $slavesCount = null;
        $hit_slaves = [];
        $hit_masters = [];
        $this->markTestSkipped('Only for master/slave');

        $mastersCount = 2;
        $slavesCount = 2;
        $retryPerNode = 10;

        $nodesCount = $mastersCount + $slavesCount;

        $hit_slaves = $hit_masters = [];

        for ($i = $nodesCount * $retryPerNode; $i-- > 0;) {
            $db = $this->prepareMasterSlave($mastersCount, $slavesCount);
            $db->setShuffleMasters(true);

            $hit_slaves[$db->getSlave()->getDriver()->getDsn()] = true;
            $hit_masters[$db->getMaster()->getDriver()->getDsn()] = true;

            if (count($hit_slaves) === $slavesCount && count($hit_masters) === $mastersCount) {
                break;
            }
        }

        $this->assertCount($mastersCount, $hit_masters, 'all masters hit');
        $this->assertCount($slavesCount, $hit_slaves, 'all slaves hit');
    }

    public function testMastersSequential(): void
    {
        $mastersCount = null;
        $slavesCount = null;
        $hit_slaves = [];
        $hit_masters = [];
        $this->markTestSkipped('Only for master/slave');

        $mastersCount = 2;
        $slavesCount = 2;
        $retryPerNode = 10;

        $nodesCount = $mastersCount + $slavesCount;

        $hit_slaves = $hit_masters = [];

        for ($i = $nodesCount * $retryPerNode; $i-- > 0;) {
            $db = $this->prepareMasterSlave($mastersCount, $slavesCount);
            $db->setShuffleMasters(false);

            $hit_slaves[$db->getSlave()->getDriver()->getDsn()] = true;
            $hit_masters[$db->getMaster()->getDriver()->getDsn()] = true;

            if (\count($hit_slaves) === $slavesCount) {
                break;
            }
        }

        $this->assertCount(1, $hit_masters, 'same master hit');
        /* slaves are always random */
        $this->assertCount($slavesCount, $hit_slaves, 'all slaves hit');
    }

    public function testQuoteValue(): void
    {
        $db = $this->getConnection();
        $quoter = $db->getQuoter();

        $this->assertEquals(123, $quoter->quoteValue(123));
        $this->assertEquals("'string'", $quoter->quoteValue('string'));
        $this->assertEquals("'It''s interesting'", $quoter->quoteValue("It's interesting"));
    }

    public function testRestoreMasterAfterException(): void
    {
        $db = null;
        $this->markTestSkipped('Only for master/slave');

        $db = $this->prepareMasterSlave(1, 1);
        $this->assertTrue($db->areSlavesEnabled());

        try {
            $db->useMaster(static function (ConnectionInterface $db) {
                throw new Exception('fail');
            });
            $this->fail('Exceptions was caught somewhere');
        } catch (Exception) {
            /* ok */
        }

        $this->assertTrue($db->areSlavesEnabled());
    }

    public function testServerStatusCacheWorks(): void
    {
        $db = null;
        $this->markTestSkipped('Only for master/slave');

        $cacheKeyNormalizer = new CacheKeyNormalizer();
        $db = $this->getConnection(true);

        $db->setMaster(
            '1',
            $this->getConnection(false, 'sqlite:' . __DIR__ . '/Runtime/yii_test_master.sq3'),
        );

        $db->setShuffleMasters(false);

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDriver()->getDsn()]
        );
        $this->assertFalse($this->cache->psr()->has($cacheKey));

        $db->open();
        $this->assertFalse(
            $this->cache->psr()->has($cacheKey),
            'Connection was successful – cache must not contain information about this DSN'
        );

        $db->close();
        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid']
        );

        $db->setMaster('1', $this->getConnection(false, 'host:invalid'));
        $db->setShuffleMasters(true);

        try {
            $db->open();
        } catch (InvalidConfigException) {
        }

        $this->assertTrue(
            $this->cache->psr()->has($cacheKey),
            'Connection was not successful – cache must contain information about this DSN'
        );
    }

    public function testServerStatusCacheCanBeDisabled(): void
    {
        $db = null;
        $this->markTestSkipped('Only for master/slave');

        $cacheKeyNormalizer = new CacheKeyNormalizer();
        $db = $this->getConnection();
        $this->cache->psr()->clear();

        $db->setMaster(
            '1',
            $this->getConnection(false, 'sqlite:' . __DIR__ . '/Runtime/yii_test_master.sq3'),
        );

        $this->schemaCache->setEnable(false);
        $db->setShuffleMasters(false);

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', $db->getDriver()->getDsn()]
        );
        $this->assertFalse($this->cache->psr()->has($cacheKey));

        $db->open();
        $this->assertFalse($this->cache->psr()->has($cacheKey), 'Caching is disabled');

        $db->close();

        $cacheKey = $cacheKeyNormalizer->normalize(
            ['Yiisoft\Db\Connection\Connection::openFromPoolSequentially', 'host:invalid']
        );
        $db->setMaster('1', $this->getConnection(false, 'host:invalid'));

        try {
            $db->open();
        } catch (InvalidConfigException) {
        }

        $this->assertFalse($this->cache->psr()->has($cacheKey), 'Caching is disabled');
    }

    public function testTransactionIsolation(): void
    {
        $db = $this->getConnection(true);
        $transaction = $db->beginTransaction(TransactionInterface::READ_UNCOMMITTED);
        $transaction->rollBack();
        $transaction = $db->beginTransaction(TransactionInterface::SERIALIZABLE);
        $transaction->rollBack();

        /* No exceptions means test is passed. */
        $this->assertTrue(true);
    }

    public function testTransactionIsolationException(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\TransactionPDO only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
        );

        $db->beginTransaction(TransactionInterface::READ_COMMITTED);
    }

    public function testTransactionShortcutCustom(): void
    {
        $db = $this->getConnection(true);

        $result = $db->transaction(static function (ConnectionInterface $db) {
            $db->createCommand()->insert('profile', ['description' => 'test transaction shortcut'])->execute();
            return true;
        }, TransactionInterface::READ_UNCOMMITTED);

        $this->assertTrue($result, 'transaction shortcut valid value should be returned from callback');

        $profilesCount = $db->createCommand(
            "SELECT COUNT(*) FROM profile WHERE description = 'test transaction shortcut';"
        )->queryScalar();

        $this->assertEquals(1, $profilesCount, 'profile should be inserted in transaction shortcut');
    }

    protected function prepareMasterSlave($masterCount, $slaveCount): ConnectionInterface
    {
        $db = null;
        $this->markTestSkipped('Only for master/slave');

        $db = $this->getConnection(true);

        for ($i = 0; $i < $masterCount; ++$i) {
            $this->getConnection(true, 'sqlite:' . __DIR__ . "/Runtime/yii_test_master{$i}.sq3");

            $db->setMaster(
                "$i",
                $this->getConnection(false, 'sqlite:' . __DIR__ . "/Runtime/yii_test_master{$i}.sq3"),
            );
        }

        for ($i = 0; $i < $slaveCount; ++$i) {
            $this->getConnection(true, 'sqlite:' . __DIR__ . "/Runtime/yii_test_slave{$i}.sq3");

            $db->setSlave(
                "$i",
                $this->getConnection(false, 'sqlite:' . __DIR__ . "/Runtime/yii_test_slave{$i}.sq3"),
            );
        }

        $db->close();

        return $db;
    }

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

    public function testSettingDefaultAttributes(): void
    {
        $db = $this->getConnection();
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $db->getActivePDO()->getAttribute(PDO::ATTR_ERRMODE));
        $db->close();
    }
}
