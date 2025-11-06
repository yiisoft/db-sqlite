<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\SqlParser;
use Yiisoft\Db\Tests\AbstractSqlParserTest;

/**
 * @group sqlite
 */
final class SqlParserTest extends AbstractSqlParserTest
{
    /** @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SqlParserProvider::getNextPlaceholder */
    public function testGetNextPlaceholder(string $sql, ?string $expectedPlaceholder, ?int $expectedPosition): void
    {
        parent::testGetNextPlaceholder($sql, $expectedPlaceholder, $expectedPosition);
    }

    protected function createSqlParser(string $sql): SqlParser
    {
        return new SqlParser($sql);
    }
}
