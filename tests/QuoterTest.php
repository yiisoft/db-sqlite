<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\AbstractQuoterTest;

/**
 * @group sqlite
 */
final class QuoterTest extends AbstractQuoterTest
{
    use TestTrait;

    #[DataProviderExternal(QuoterProvider::class, 'tableNameParts')]
    public function testGetTableNameParts(string $tableName, array $expected): void
    {
        parent::testGetTableNameParts($tableName, $expected);
    }
}
