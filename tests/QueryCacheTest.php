<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQueryCacheTest;

/**
 * @group sqlite
 */
final class QueryCacheTest extends CommonQueryCacheTest
{
    use TestTrait;
}
