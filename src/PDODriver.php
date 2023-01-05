<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use PDO;
use Yiisoft\Db\Driver\PDO\AbstractPDODriver;

final class PDODriver extends AbstractPDODriver
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
