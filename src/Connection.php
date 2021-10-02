<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use PDO;
use Yiisoft\Db\Connection\Connection as AbstractConnection;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Cache\SchemaCache;

use function constant;
use function strncmp;
use function substr;

/**
 * Database connection class prefilled for MYSQL Server.
 */
final class Connection extends AbstractConnection
{
    private QueryCache $queryCache;
    private SchemaCache $schemaCache;

    public function __construct(string $dsn, QueryCache $queryCache, SchemaCache $schemaCache)
    {
        $this->queryCache = $queryCache;
        $this->schemaCache = $schemaCache;

        parent::__construct($dsn, $queryCache);
    }

    /**
     * Creates a command for execution.
     *
     * @param string|null $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     *
     * @return Command the DB command
     */
    public function createCommand(string $sql = null, array $params = []): Command
    {
        if ($sql !== null) {
            $sql = $this->quoteSql($sql);
        }

        $command = new Command($this, $this->queryCache, $sql);

        if ($this->logger !== null) {
            $command->setLogger($this->logger);
        }

        if ($this->profiler !== null) {
            $command->setProfiler($this->profiler);
        }

        return $command->bindValues($params);
    }

    /**
     * Returns the schema information for the database opened by this connection.
     *
     * @return Schema the schema information for the database opened by this connection.
     */
    public function getSchema(): Schema
    {
        return new Schema($this, $this->schemaCache);
    }

    /**
     * Creates the PDO instance.
     *
     * This method is called by {@see open} to establish a DB connection. The default implementation will create a PHP
     * PDO instance. You may override this method if the default PDO needs to be adapted for certain DBMS.
     *
     * @return PDO the pdo instance
     */
    protected function createPdoInstance(): PDO
    {
        $dsn = $this->getDsn();

        if (strncmp('sqlite:@', $dsn, 8) === 0) {
            $dsn = 'sqlite:' . substr($dsn, 7);
        }

        return new PDO($dsn, $this->getUsername(), $this->getPassword(), $this->getAttributes());
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
        $pdo = $this->getPDO();

        if ($pdo !== null) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($this->getEmulatePrepare() !== null && constant('PDO::ATTR_EMULATE_PREPARES')) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $this->getEmulatePrepare());
            }
        }
    }

    /**
     * Returns the name of the DB driver.
     *
     * @return string name of the DB driver
     */
    public function getDriverName(): string
    {
        return 'sqlite';
    }
}
