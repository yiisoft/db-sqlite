<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Throwable;
use Yiisoft\Db\Driver\PDO\TransactionPDO as AbstractTransactionPDO;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Transaction\TransactionInterface;

final class TransactionPDO extends AbstractTransactionPDO
{
    /**
     * Sets the isolation level of the current transaction.
     *
     * @param string $level The transaction isolation level to use for this transaction. This can be either
     * {@see TransactionInterface::READ_UNCOMMITTED} or {@see TransactionInterface::SERIALIZABLE}.
     *
     * @throws Exception|InvalidConfigException|NotSupportedException|Throwable when unsupported isolation levels are
     * used. SQLite only supports SERIALIZABLE and READ UNCOMMITTED.
     *
     * {@see http://www.sqlite.org/pragma.html#pragma_read_uncommitted}
     */
    protected function setTransactionIsolationLevel(string $level): void
    {
        switch ($level) {
            case self::SERIALIZABLE:
                $this->db->createCommand('PRAGMA read_uncommitted = False;')->execute();
                break;
            case self::READ_UNCOMMITTED:
                $this->db->createCommand('PRAGMA read_uncommitted = True;')->execute();
                break;
            default:
                throw new NotSupportedException(
                    self::class . ' only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.'
                );
        }
    }
}
