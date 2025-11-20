<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\IntegrationTestTrait;
use Yiisoft\Db\Tests\Common\CommonPdoCommandTest;

/**
 * @group sqlite
 */
final class PdoCommandTest extends CommonPdoCommandTest
{
    use IntegrationTestTrait;
}
