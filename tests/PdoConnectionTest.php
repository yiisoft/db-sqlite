<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Tests\Support\IntegrationTestTrait;
use Yiisoft\Db\Tests\Common\CommonPdoConnectionTest;
use Yiisoft\Db\Tests\Support\TestHelper;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * @group sqlite
 */
final class PdoConnectionTest extends CommonPdoConnectionTest
{
    use IntegrationTestTrait;

    /**
     * Ensure database connection is reset on when a connection is cloned.
     *
     * Make sure each connection element has its own PDO instance i.e. own connection to the DB.
     * Also, transaction elements should not be shared between two connections.
     */
    public function testClone(): void
    {
        $db = new Connection(
            new Driver('sqlite:' . __DIR__ . '/Support/Runtime/yiitest.sq3'),
            TestHelper::createMemorySchemaCache(),
        );

        $this->assertNull($db->getTransaction());
        $this->assertNull($db->getPdo());

        $db->open();

        $this->assertNull($db->getTransaction());
        $this->assertNotNull($db->getPdo());

        $conn2 = clone $db;

        $this->assertNull($db->getTransaction());
        $this->assertNotNull($db->getPdo());

        $this->assertNull($conn2->getTransaction());
        $this->assertNull($conn2->getPdo());

        $db->beginTransaction();

        $this->assertNotNull($db->getTransaction());
        $this->assertNotNull($db->getPdo());

        $this->assertNull($conn2->getTransaction());
        $this->assertNull($conn2->getPdo());

        $conn3 = clone $db;

        $this->assertNotNull($db->getTransaction());
        $this->assertNotNull($db->getPdo());
        $this->assertNull($conn3->getTransaction());
        $this->assertNull($conn3->getPdo());
    }

    public function testGetLastInsertId(): void
    {
        $db = $this->getSharedConnection();
        $this->loadFixture();

        $command = $db->createCommand();
        $command->insert(
            'customer',
            [
                'name' => 'Some {{weird}} name',
                'email' => 'test@example.com',
                'address' => 'Some {{%weird}} address',
            ],
        )->execute();

        $this->assertSame('4', $db->getLastInsertId());
    }

    public function testTransactionIsolationException(): void
    {
        $db = $this->getSharedConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Transaction only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.',
        );

        $db->beginTransaction(TransactionInterface::READ_COMMITTED);
    }
}
