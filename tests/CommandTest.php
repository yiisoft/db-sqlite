<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\TestSupport\TestCommandTrait;

use function version_compare;

/**
 * @group sqlite
 */
final class CommandTest extends TestCase
{
    use TestCommandTrait;

    protected string $upsertTestCharCast = 'CAST([[address]] AS VARCHAR(255))';

    public function testAddDropForeignKey(): void
    {
        $db = $this->getConnection();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.'
        );
        $db->createCommand()->addForeignKey(
            'test_fk_constraint',
            'students',
            ['Department_id'],
            'departments',
            ['Department_id']
        )->execute();
    }

    public function testAddDropUnique(): void
    {
        $db = $this->getConnection();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique is not supported by SQLite.'
        );
        $db->createCommand()->addUnique('test_fk_constraint', 'students', ['Department_id'])->execute();
    }

    public function testAutoQuoting(): void
    {
        $db = $this->getConnection();
        $sql = 'SELECT [[id]], [[t.name]] FROM {{customer}} t';
        $command = $db->createCommand($sql);
        $this->assertEquals('SELECT `id`, `t`.`name` FROM `customer` t', $command->getSql());
    }

    public function testDropForeignKey(): void
    {
        $db = $this->getConnection();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.'
        );
        $db->createCommand()->dropForeignKey('departments', 'test_fk_constraint')->execute();
    }

    public function testDropUnique(): void
    {
        $db = $this->getConnection();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique is not supported by SQLite.'
        );
        $db->createCommand()->dropUnique('departments', 'test_fk_constraint')->execute();
    }

    public function testForeingKeyException(): void
    {
        $db = $this->getConnection();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.'
        );
        $db->createCommand()->addForeignKey(
            'test_fk_constraint',
            'students',
            ['Department_id'],
            'departments',
            ['Department_id']
        )->execute();
    }

    public function testMultiStatementSupport(): void
    {
        $db = $this->getConnection(false);
        $sql = <<<SQL
        DROP TABLE IF EXISTS {{T_multistatement}};
        CREATE TABLE {{T_multistatement}} (
            [[intcol]] INTEGER,
            [[textcol]] TEXT
        );
        INSERT INTO {{T_multistatement}} VALUES(41, :val1);
        INSERT INTO {{T_multistatement}} VALUES(42, :val2);
        SQL;

        // check
        $db->createCommand($sql, ['val1' => 'foo', 'val2' => 'bar'])->execute();
        $queryAll = $db->createCommand('SELECT * FROM {{T_multistatement}}')->queryAll();

        if (version_compare(PHP_VERSION, '8.1', '>=')) {
            $this->assertSame(
                [['intcol' => 41, 'textcol' => 'foo'], [ 'intcol' => 42, 'textcol' => 'bar']],
                $queryAll,
            );
        } else {
            $this->assertSame(
                [['intcol' => '41', 'textcol' => 'foo'], [ 'intcol' => '42', 'textcol' => 'bar']],
                $queryAll,
            );
        }

        $sql = <<<SQL
        UPDATE {{T_multistatement}} SET [[intcol]] = :newInt WHERE [[textcol]] = :val1;
        DELETE FROM {{T_multistatement}} WHERE [[textcol]] = :val2;
        SELECT * FROM {{T_multistatement}}
        SQL;

        // check
        $queryAll = $db->createCommand($sql, ['newInt' => 410, 'val1' => 'foo', 'val2' => 'bar'])->queryAll();

        if (version_compare(PHP_VERSION, '8.1', '>=')) {
            $this->assertSame([['intcol' => 410, 'textcol' => 'foo']], $queryAll);
        } else {
            $this->assertSame([['intcol' => '410', 'textcol' => 'foo']], $queryAll);
        }
    }

    public function batchInsertSqlProvider(): array
    {
        $parent = $this->batchInsertSqlProviderTrait();
        /* Produces SQL syntax error: General error: 1 near ".": syntax error */
        unset($parent['wrongBehavior']);
        return $parent;
    }

    /**
     * Make sure that `{{something}}` in values will not be encoded.
     *
     * @dataProvider batchInsertSqlProvider
     *
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param string $expected
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * {@see https://github.com/yiisoft/yii2/issues/11242}
     */
    public function testBatchInsertSQL(
        string $table,
        array $columns,
        array $values,
        string $expected,
        array $expectedParams = []
    ): void {
        $db = $this->getConnection(true);
        $command = $db->createCommand();
        $command->batchInsert($table, $columns, $values);
        $command->prepare(false);
        $this->assertSame($expected, $command->getSql());
        $this->assertSame($expectedParams, $command->getParams());
    }

    /**
     * Test whether param binding works in other places than WHERE.
     *
     * @dataProvider bindParamsNonWhereProviderTrait
     *
     * @param string $sql
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBindParamsNonWhere(string $sql): void
    {
        $db = $this->getConnection();
        $db->createCommand()->insert(
            'customer',
            [
                'name' => 'testParams',
                'email' => 'testParams@example.com',
                'address' => '1',
            ]
        )->execute();
        $params = [':email' => 'testParams@example.com', ':len' => 5];
        $command = $db->createCommand($sql, $params);
        $this->assertEquals('Params', $command->queryScalar());
    }

    /**
     * Test command getRawSql.
     *
     * @dataProvider getRawSqlProviderTrait
     *
     * @param string $sql
     * @param array $params
     * @param string $expectedRawSql
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     *
     * {@see https://github.com/yiisoft/yii2/issues/8592}
     */
    public function testGetRawSql(string $sql, array $params, string $expectedRawSql): void
    {
        $db = $this->getConnection();
        $command = $db->createCommand($sql, $params);
        $this->assertEquals($expectedRawSql, $command->getRawSql());
    }

    /**
     * Test INSERT INTO ... SELECT SQL statement with wrong query object.
     *
     * @dataProvider invalidSelectColumnsProviderTrait
     *
     * @param mixed $invalidSelectColumns
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testInsertSelectFailed($invalidSelectColumns): void
    {
        $db = $this->getConnection();
        $query = new Query($db);
        $query->select($invalidSelectColumns)->from('{{customer}}');
        $command = $db->createCommand();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected select query object with enumerated (named) parameters');
        $command->insert('{{customer}}', $query)->execute();
    }

    /**
     * @dataProvider upsertProviderTrait
     *
     * @param array $firstData
     * @param array $secondData
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testUpsert(array $firstData, array $secondData)
    {
        if (version_compare($this->getConnection()->getServerVersion(), '3.8.3', '<')) {
            $this->markTestSkipped('SQLite < 3.8.3 does not support "WITH" keyword.');
            return;
        }

        $db = $this->getConnection(true);
        $this->assertEquals(0, $db->createCommand('SELECT COUNT(*) FROM {{T_upsert}}')->queryScalar());
        $this->performAndCompareUpsertResult($db, $firstData);
        $this->assertEquals(1, $db->createCommand('SELECT COUNT(*) FROM {{T_upsert}}')->queryScalar());
        $this->performAndCompareUpsertResult($db, $secondData);
    }
}
