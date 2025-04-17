<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Driver\Pdo\AbstractPdoConnection;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\Column\ColumnFactoryInterface;
use Yiisoft\Db\Schema\Quoter;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Column\ColumnFactory;

use function str_starts_with;

/**
 * Implements a connection to a database via PDO (PHP Data Objects) for SQLite Server.
 *
 * @link https://www.php.net/manual/en/ref.pdo-sqlite.php
 */
final class Connection extends AbstractPdoConnection
{
    /**
     * Reset the connection after cloning.
     */
    public function __clone()
    {
        $this->transaction = null;

        if (!str_starts_with($this->driver->getDsn(), 'sqlite::memory:')) {
            /** Reset PDO connection, unless its sqlite in-memory, which can only have one connection. */
            $this->pdo = null;
        }
    }

    public function createCommand(?string $sql = null, array $params = []): Command
    {
        $command = new Command($this);

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

    public function createTransaction(): Transaction
    {
        return new Transaction($this);
    }

    public function getColumnFactory(): ColumnFactoryInterface
    {
        return $this->columnFactory ??= new ColumnFactory();
    }

    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder ??= new QueryBuilder($this);
    }

    public function getQuoter(): QuoterInterface
    {
        return $this->quoter ??= new Quoter('`', '`', $this->getTablePrefix());
    }

    public function getSchema(): SchemaInterface
    {
        return $this->schema ??= new Schema($this, $this->schemaCache);
    }
}
