<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonConnectionPDOTest;

/**
 * @group sqlite
 */
final class ConnectionPDOTest extends CommonConnectionPDOTest
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
        $dsn = 'sqlite:' . __DIR__ . '/Support/Runtime/yiitest.sq3';
        $db = $this->getConnectionWithDsn($dsn);

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
}
