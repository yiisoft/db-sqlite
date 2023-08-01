<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonPdoConnectionTest;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class PdoConnectionTest extends CommonPdoConnectionTest
{
    use TestTrait;

    /**
     * Ensure database connection is reset on when a connection is cloned.
     *
     * Make sure each connection element has its own PDO instance i.e. own connection to the DB.
     * Also, transaction elements should not be shared between two connections.
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testClone(): void
    {
        $this->setDsn('sqlite:' . __DIR__ . '/Support/Runtime/yiitest.sq3');

        $db = $this->getConnection();

        $this->assertNull($db->getTransaction());
        $this->assertNull($db->getPDO());

        $db->open();

        $this->assertNull($db->getTransaction());
        $this->assertNotNull($db->getPDO());

        $conn2 = clone $db;

        $this->assertNull($db->getTransaction());
        $this->assertNotNull($db->getPDO());

        $this->assertNull($conn2->getTransaction());
        $this->assertNull($conn2->getPDO());

        $db->beginTransaction();

        $this->assertNotNull($db->getTransaction());
        $this->assertNotNull($db->getPDO());

        $this->assertNull($conn2->getTransaction());
        $this->assertNull($conn2->getPDO());

        $conn3 = clone $db;

        $this->assertNotNull($db->getTransaction());
        $this->assertNotNull($db->getPDO());
        $this->assertNull($conn3->getTransaction());
        $this->assertNull($conn3->getPDO());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidCallException
     * @throws Throwable
     */
    public function testGetLastInsertID(): void
    {
        $db = $this->getConnection(true);

        $command = $db->createCommand();
        $command->insert(
            'customer',
            [
                'name' => 'Some {{weird}} name',
                'email' => 'test@example.com',
                'address' => 'Some {{%weird}} address',
            ]
        )->execute();

        $this->assertSame('4', $db->getLastInsertID());

        $db->close();
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
            'Yiisoft\Db\Sqlite\Transaction only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
        );

        $db->beginTransaction(TransactionInterface::READ_COMMITTED);
    }
}
