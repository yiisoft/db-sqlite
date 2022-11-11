<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Sqlite\SqlToken;

/**
 * @group sqlite
 */
final class SqlTokenTest extends TestCase
{
    public function testGetContent(): void
    {
        $token = new SqlToken();
        $token->content('foo');

        $this->assertSame('foo', $token->getContent());
    }

    public function testSetChildren(): void
    {
        $token = new SqlToken();
        $token->content('foo');
        $token->setChildren(
            [(new SqlToken())->content('bar'), (new SqlToken())->content('baz')],
        );

        $this->assertSame('foo', $token->getContent());
        $this->assertCount(2, $token->getChildren());
        $this->assertSame('bar', $token->getChildren()[0]->getContent());
        $this->assertSame('baz', $token->getChildren()[1]->getContent());
    }
}
