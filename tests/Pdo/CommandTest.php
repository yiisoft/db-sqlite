<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Pdo;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class CommandTest extends \Yiisoft\Db\Tests\Common\Pdo\CommonCommandTest
{
    use TestTrait;
}
