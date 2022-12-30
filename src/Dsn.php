<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Connection\AbstractDsn;

/**
 * The Dsn class is typically used to parse a DSN string, which is a string that contains all the necessary information
 * to connect to a database SQL Server, such as the database driver, database name.
 *
 * It also allows you to access individual components of the DSN, such as the driver, database name.
 *
 * @link https://www.php.net/manual/en/ref.pdo-sqlite.connection.php
 */
final class Dsn extends AbstractDsn
{
    /**
     * @psalm-param string[] $options
     */
    public function __construct(private string $driver, private string $databaseName = '')
    {
        parent::__construct($driver, '', $databaseName);
    }

    /**
     * @return string The Data Source Name, or DSN, contains the information required to connect to the database.
     *
     * Please refer to the [PHP manual](http://php.net/manual/en/pdo.construct.php) on the format of the DSN string.
     *
     * The `driver` array key is used as the driver prefix of the DSN, all further key-value pairs are rendered as
     * `key=value` and concatenated by `;`. For example:
     *
     * ```php
     * $dsn = new Dsn('sqlite', __DIR__ . '/data/test.sq3');
     * $pdoDriver = new PDODriver($dsn->asString());
     * $db = new ConnectionPDO($pdoDriver, $queryCache, $schemaCache);
     * ```
     *
     * Will result in the DSN string `sqlite:/path/to/data/test.sq3`.
     */
    public function asString(): string
    {
        $dsn = match ($this->databaseName) {
            'memory' => $this->driver . '::memory:',
            '' => $this->driver . ':',
            default => $this->driver . ':' . $this->databaseName,
        };

        return $dsn;
    }
}
