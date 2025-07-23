<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Sqlite\Dsn;

/**
 * @group sqlite
 */
final class DsnTest extends TestCase
{
    public function testConstruct(): void
    {
        $dsn = new Dsn('sqlite', __DIR__ . '/runtime/yiitest.sq3');

        $this->assertSame('sqlite', $dsn->driver);
        $this->assertSame(__DIR__ . '/runtime/yiitest.sq3', $dsn->databaseName);
        $this->assertSame('sqlite:' . __DIR__ . '/runtime/yiitest.sq3', (string) $dsn);
    }

    public function testConstructDefaults(): void
    {
        $dsn = new Dsn();

        $this->assertSame('sqlite', $dsn->driver);
        $this->assertSame('', $dsn->databaseName);
        $this->assertSame('sqlite:', (string) $dsn);
    }

    public function testConstructWithMemory(): void
    {
        $dsn = new Dsn('sqlite', 'memory');

        $this->assertSame('sqlite', $dsn->driver);
        $this->assertSame('memory', $dsn->databaseName);
        $this->assertSame('sqlite::memory:', (string) $dsn);
    }
}
