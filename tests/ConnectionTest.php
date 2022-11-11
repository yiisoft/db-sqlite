<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Throwable;
use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonConnectionTest;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ConnectionTest extends CommonConnectionTest
{
    use TestTrait;

    /**
     * @throws Exception
     */
    public function testGetDriverName(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('sqlite', $db->getDriver()->getDriverName());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
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
     * @throws Exception
     */
    public function testQuoteValue(): void
    {
        $db = $this->getConnection();

        $quoter = $db->getQuoter();

        $this->assertEquals(123, $quoter->quoteValue(123));
        $this->assertEquals("'string'", $quoter->quoteValue('string'));
        $this->assertEquals("'It''s interesting'", $quoter->quoteValue("It's interesting"));
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     */
    public function testTransactionIsolation(): void
    {
        $db = $this->getConnection();

        $transaction = $db->beginTransaction(TransactionInterface::READ_UNCOMMITTED);
        $transaction->rollBack();
        $transaction = $db->beginTransaction(TransactionInterface::SERIALIZABLE);
        $transaction->rollBack();

        /* No exceptions' means test is passed. */
        $this->assertTrue(true);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testTransactionIsolationException(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\TransactionPDO only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
        );

        $db->beginTransaction(TransactionInterface::READ_COMMITTED);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testTransactionShortcutCustom(): void
    {
        $db = $this->getConnectionWithData();

        $result = $db->transaction(static function (ConnectionPDOInterface $db) {
            $db->createCommand()->insert('profile', ['description' => 'test transaction shortcut'])->execute();

            return true;
        }, TransactionInterface::READ_UNCOMMITTED);

        $this->assertTrue($result, 'transaction shortcut valid value should be returned from callback');

        $profilesCount = $db->createCommand(
            <<<SQL
            SELECT COUNT(*) FROM profile WHERE description = 'test transaction shortcut'
            SQL
        )->queryScalar();

        $this->assertEquals(1, $profilesCount, 'profile should be inserted in transaction shortcut');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testSettingDefaultAttributes(): void
    {
        $db = $this->getConnection();

        $this->assertSame(
            PDO::ERRMODE_EXCEPTION,
            $db->getActivePDO()?->getAttribute(PDO::ATTR_ERRMODE),
        );
    }
}
