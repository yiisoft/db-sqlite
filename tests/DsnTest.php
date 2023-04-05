<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Sqlite\Dsn;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class DsnTest extends TestCase
{
    public function testAsString(): void
    {
        $this->assertSame(
            'sqlite:' . __DIR__ . '/runtime/yiitest.sq3',
            (new Dsn('sqlite', __DIR__ . '/runtime/yiitest.sq3'))->asString(),
        );
    }

    public function testAsStringWithDatabaseName(): void
    {
        $this->assertSame('sqlite:', (new Dsn('sqlite'))->asString());
    }

    public function testAsStringWithDatabaseNameWithEmptyString(): void
    {
        $this->assertSame('sqlite:', (new Dsn('sqlite', ''))->asString());
    }

    public function testAsStringWithDatabaseNameWithNull(): void
    {
        $this->assertSame('sqlite:', (new Dsn('sqlite', null))->asString());
    }

    public function testAsStringWithMemory(): void
    {
        $this->assertSame('sqlite::memory:', (new Dsn('sqlite', 'memory'))->asString());
    }
}
