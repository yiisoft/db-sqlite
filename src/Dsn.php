<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Connection\AbstractDsn;

/**
 * Implement a Data Source Name (DSN) for an SQLite Server.
 *
 * @link https://www.php.net/manual/en/ref.pdo-sqlite.connection.php
 */
final class Dsn extends AbstractDsn
{
    /**
     * @psalm-param string[] $options
     */
    public function __construct(private string $driver, private string|null $databaseName = null)
    {
        parent::__construct($driver, '', $databaseName);
    }

    /**
     * @return string The Data Source Name, or DSN, has the information required to connect to the database.
     *
     * Please refer to the [PHP manual](http://php.net/manual/en/pdo.construct.php) on the format of the DSN string.
     *
     * The `driver` array key is used as the driver prefix of the DSN, all further key-value pairs are rendered as
     * `key=value` and concatenated by `;`. For example:
     *
     * ```php
     * $dsn = new Dsn('sqlite', __DIR__ . '/data/test.sq3');
     * $driver = new Driver($dsn->asString());
     * $db = new Connection($driver, $schemaCache);
     * ```
     *
     * Will result in the DSN string `sqlite:/path/to/data/test.sq3`.
     */
    public function asString(): string
    {
        return match ($this->databaseName) {
            '' => $this->driver . ':',
            'memory' => $this->driver . '::memory:',
            null => $this->driver . ':',
            default => $this->driver . ':' . $this->databaseName,
        };
    }
}
