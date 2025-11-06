<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Stringable;

/**
 * Represents a Data Source Name (DSN) for a SQLite Server that's used to configure a {@see Driver} instance.
 *
 * To get DSN in string format, use the `(string)` type casting operator.
 *
 * @link https://www.php.net/manual/en/ref.pdo-sqlite.connection.php
 */
final class Dsn implements Stringable
{
    /**
     * @param string $driver The database driver name.
     * @param string $databaseName The database name to connect to. It can be
     * - absolute file path for a file database;
     * - 'memory' for a database in memory;
     * - empty string for a temporary file database which is deleted when the connection is closed.
     */
    public function __construct(
        public readonly string $driver = 'sqlite',
        public readonly string $databaseName = '',
    ) {}

    /**
     * @return string The Data Source Name, or DSN, has the information required to connect to the database.
     *
     * Please refer to the [PHP manual](https://php.net/manual/en/pdo.construct.php) on the format of the DSN string.
     *
     * The `driver` property is used as the driver prefix of the DSN. For example:
     *
     * ```php
     * $dsn = new Dsn('sqlite', __DIR__ . '/data/test.sq3');
     * $driver = new Driver($dsn);
     * $db = new Connection($driver, $schemaCache);
     * ```
     *
     * Will result in the DSN string `sqlite:/path/to/data/test.sq3`.
     */
    public function __toString(): string
    {
        if ($this->databaseName === 'memory') {
            return "$this->driver::memory:";
        }

        return "$this->driver:$this->databaseName";
    }
}
