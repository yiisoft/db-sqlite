<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use PDO;
use Yiisoft\Db\Driver\Pdo\AbstractPdoDriver;

/**
 * Implements the SQLite Server driver based on the PDO (PHP Data Objects) extension.
 *
 * @link https://www.php.net/manual/en/ref.pdo-sqlite.php
 */
final class Driver extends AbstractPdoDriver
{
    public function createConnection(): PDO
    {
        $this->attributes += [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        if (PHP_VERSION_ID >= 80100) {
            $this->attributes += [PDO::ATTR_STRINGIFY_FETCHES => true];
        }

        return parent::createConnection();
    }

    public function getDriverName(): string
    {
        return 'sqlite';
    }
}
