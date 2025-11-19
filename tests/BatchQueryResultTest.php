<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\IntegrationTestTrait;
use Yiisoft\Db\Tests\Common\CommonBatchQueryResultTest;

/**
 * @group sqlite
 */
final class BatchQueryResultTest extends CommonBatchQueryResultTest
{
    use IntegrationTestTrait;
}
