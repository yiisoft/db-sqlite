<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\PDO;

use PDO;
use Yiisoft\Db\Driver\PDO\CommandPDOInterface;
use Yiisoft\Db\Driver\PDO\ConnectionPDO;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Query\QueryBuilderInterface;
use Yiisoft\Db\Schema\Quoter;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Schema;
use Yiisoft\Db\Transaction\TransactionInterface;

use function constant;
use function strncmp;

/**
 * Database connection class prefilled for SQLite Server.
 */
final class ConnectionPDOSqlite extends ConnectionPDO
{
    /**
     * Reset the connection after cloning.
     */
    public function __clone()
    {
        $this->transaction = null;

        if (strncmp($this->driver->getDsn(), 'sqlite::memory:', 15) !== 0) {
            /** reset PDO connection, unless its sqlite in-memory, which can only have one connection */
            $this->pdo = null;
        }
    }

    public function createCommand(?string $sql = null, array $params = []): CommandPDOInterface
    {
        $command = new CommandPDOSqlite($this, $this->queryCache);

        if ($sql !== null) {
            $command->setSql($sql);
        }

        if ($this->logger !== null) {
            $command->setLogger($this->logger);
        }

        if ($this->profiler !== null) {
            $command->setProfiler($this->profiler);
        }

        return $command->bindValues($params);
    }

    public function createTransaction(): TransactionInterface
    {
        return new TransactionPDOSqlite($this);
    }

    public function getDriverName(): string
    {
        return 'sqlite';
    }

    /**
     * @throws Exception|InvalidConfigException
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new QueryBuilderPDOSqlite(
                $this->getQuoter(),
                $this->getSchema(),
            );
        }

        return $this->queryBuilder;
    }

    public function getQuoter(): QuoterInterface
    {
        if ($this->quoter === null) {
            $this->quoter = new Quoter('`', '`', $this->getTablePrefix());
        }

        return $this->quoter;
    }

    public function getSchema(): SchemaInterface
    {
        if ($this->schema === null) {
            $this->schema = new Schema($this, $this->schemaCache);
        }

        return $this->schema;
    }

    /**
     * Initializes the DB connection.
     *
     * This method is invoked right after the DB connection is established.
     *
     * The default implementation turns on `PDO::ATTR_EMULATE_PREPARES`.
     *
     * if {@see emulatePrepare} is true, and sets the database {@see charset} if it is not empty.
     *
     * It then triggers an {@see EVENT_AFTER_OPEN} event.
     */
    protected function initConnection(): void
    {
        $attributes = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if ($this->getEmulatePrepare() !== null && constant('PDO::ATTR_EMULATE_PREPARES')) {
            $attributes[PDO::ATTR_EMULATE_PREPARES] = $this->getEmulatePrepare();
        }
        $this->driver->attributes($attributes);

        $this->pdo = $this->driver->createConnection();
    }
}
