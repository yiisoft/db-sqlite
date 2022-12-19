<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonCommandPDOTest;

/**
 * @group sqlite
 */
final class CommandPDOTest extends CommonCommandPDOTest
{
    use TestTrait;
}
