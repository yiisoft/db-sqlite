<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQuoterTest;

/**
 * @group sqlite
 */
final class QuoterTest extends CommonQuoterTest
{
    use TestTrait;

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider::columnNames()
     */
    public function testQuoteColumnNameWithDbGetQuoter(string $columnName, string $expected): void
    {
        parent::testQuoteColumnNameWithDbGetQuoter($columnName, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider::simpleColumnNames()
     */
    public function testQuoteSimpleColumnNameWithDbGetQuoter(string $columnName, string $expected): void
    {
        parent::testQuoteSimpleColumnNameWithDbGetQuoter($columnName, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider::simpleTableNames()
     */
    public function testQuoteSimpleTableNameWithDbGetQuoter(string $tableName, string $expected): void
    {
        parent::testQuoteSimpleTableNameWithDbGetQuoter($tableName, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider::unquoteSimpleColumnName()
     */
    public function testUnquoteSimpleColumnNameWithDbGetQuoter(string $tableName, string $expected): void
    {
        parent::testUnquoteSimpleColumnNameWithDbGetQuoter($tableName, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QuoterProvider::unquoteSimpleTableName()
     */
    public function testUnquoteSimpleTableNameWithDbGetQuoter(string $tableName, string $expected): void
    {
        parent::testUnquoteSimpleTableNameWithDbGetQuoter($tableName, $expected);
    }
}
