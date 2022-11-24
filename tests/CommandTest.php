<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonCommandTest;

use function version_compare;

/**
 * @group sqlite
 *
 * @psalm-suppress MixedMethodCall
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class CommandTest extends CommonCommandTest
{
    use TestTrait;

    protected string $upsertTestCharCast = 'CAST([[address]] AS VARCHAR(255))';

    public function testAddCheck(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck() is not supported by SQLite.'
        );

        parent::testAddCheck();
    }

    public function testAddCommentOnColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn() is not supported by SQLite.'
        );

        parent::testAddCommentOnColumn();
    }

    public function testAddCommentOnTable(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnTable() is not supported by SQLite.'
        );

        parent::testAddCommentOnTable();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddDefaultValue(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\QueryBuilder\DDLQueryBuilder::addDefaultValue() does not support adding default value constraints.'
        );

        $command->addDefaultValue('name', 'table', 'column', 'value');
    }

    public function testAddForeignKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey() is not supported by SQLite.');

        parent::testAddForeignKey();
    }

    public function testAddPrimaryKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey() is not supported by SQLite.');

        parent::testAddPrimaryKey();
    }

    public function testAddUnique(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique() is not supported by SQLite.');

        parent::testAddUnique();
    }

    public function testAlterColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn() is not supported by SQLite.');

        parent::testAlterColumn();
    }

    public function testAlterTable(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn() is not supported by SQLite.');

        parent::testAlterTable();
    }

    /**
     * Make sure that `{{something}}` in values will not be encoded.
     *
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::batchInsertSql()
     *
     * {@see https://github.com/yiisoft/yii2/issues/11242}
     */
    public function testBatchInsertSQL(
        string $table,
        array $columns,
        array $values,
        string $expected,
        array $expectedParams = [],
        int $insertedRow = 1,
        string $fixture = 'type'
    ): void {
        parent::testBatchInsertSQL($table, $columns, $values, $expected, $expectedParams, $insertedRow, $fixture);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testCheckIntegrity(): void
    {
        $db = $this->getConnection('customer');

        $command = $db->createCommand();
        $command->checkIntegrity('', 'customer');

        $this->assertSame(
            <<<SQL
            PRAGMA foreign_keys=1
            SQL,
            $command->getSql()
        );
        $this->assertSame(1, $command->execute());
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::createIndex()
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testCreateIndex(
        string $name,
        string $table,
        array|string $column,
        string $indexType = '',
        string $indexMethod = '',
        string $expected = '',
    ): void {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $schema = $db->getSchema();

        if ($schema->getTableSchema($table) !== null) {
            $command->dropTable($table)->execute();
        }

        $command->createTable($table, ['int1' => 'integer not null', 'int2' => 'integer not null'])->execute();

        $this->assertEmpty($schema->getTableIndexes($table, true));

        $command->createIndex($name, $table, $column, $indexType, $indexMethod)->execute();

        $this->assertSame($column, $schema->getTableIndexes($table, true)[0]->getColumnNames());

        if ($indexType === 'UNIQUE') {
            $this->assertTrue($schema->getTableIndexes($table, true)[0]->isUnique());
        } else {
            $this->assertFalse($schema->getTableIndexes($table, true)[0]->isUnique());
        }
    }

    public function testDropCheck(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCheck() is not supported by SQLite.');

        parent::testDropCheck();
    }

    public function testDropColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropColumn() is not supported by SQLite.');

        parent::testDropColumn();
    }

    public function testDropCommentFromColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromColumn() is not supported by SQLite.'
        );

        parent::testDropCommentFromColumn();
    }

    public function testDropCommentFromTable(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromTable() is not supported by SQLite.'
        );

        parent::testDropCommentFromTable();
    }

    public function testDropForeingKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey() is not supported by SQLite.'
        );

        parent::testDropForeingKey();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testDropIndex(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $schema = $db->getSchema();

        if ($schema->getTableSchema('test_idx') !== null) {
            $command->dropTable('test_idx')->execute();
        }

        $command->createTable('test_idx', ['int1' => 'integer not null', 'int2' => 'integer not null'])->execute();

        $this->assertEmpty($schema->getTableIndexes('test_idx', true));

        $command->createIndex('test_idx_constraint', 'test_idx', ['int1', 'int2'], 'UNIQUE')->execute();

        $this->assertSame(['int1', 'int2'], $schema->getTableIndexes('test_idx', true)[0]->getColumnNames());
        $this->assertTrue($schema->getTableIndexes('test_idx', true)[0]->isUnique());

        $command->dropIndex('test_idx_constraint', 'test_idx')->execute();

        $this->assertEmpty($schema->getTableIndexes('test_idx', true));
    }

    public function testDropPrimaryKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey() is not supported by SQLite.'
        );

        parent::testDropPrimaryKey();
    }

    public function testDropUnique(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique() is not supported by SQLite.'
        );

        parent::testDropUnique();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testMultiStatementSupport(): void
    {
        $db = $this->getConnection();

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
        $queryAll = $db->createCommand(
            <<<SQL
            SELECT * FROM {{T_multistatement}}
            SQL
        )->queryAll();

        $this->assertEquals(
            [['intcol' => 41, 'textcol' => 'foo'], [ 'intcol' => 42, 'textcol' => 'bar']],
            $queryAll,
        );

        $sql = <<<SQL
        UPDATE {{T_multistatement}} SET [[intcol]] = :newInt WHERE [[textcol]] = :val1;
        DELETE FROM {{T_multistatement}} WHERE [[textcol]] = :val2;
        SELECT * FROM {{T_multistatement}}
        SQL;

        $queryAll = $db->createCommand($sql, ['newInt' => 410, 'val1' => 'foo', 'val2' => 'bar'])->queryAll();

        $this->assertEquals([['intcol' => 410, 'textcol' => 'foo']], $queryAll);
    }

    public function testRenameColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::renameColumn() is not supported by SQLite.'
        );

        parent::testRenameColumn();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testRenameTable(): void
    {
        $db = $this->getConnection('customer');

        $command = $db->createCommand();
        $command->renameTable('customer', 'customer_new')->execute();

        $this->assertSame('customer_new', $db->getSchema()->getTableSchema('customer_new')?->getName());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testResetSequence(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        if ($db->getSchema()->getTableSchema('testCreateTable', true) !== null) {
            $command->dropTable('testCreateTable')->execute();
        }

        $command->createTable('testCreateTable', ['id' => Schema::TYPE_PK, 'bar' => Schema::TYPE_INTEGER])->execute();

        $command->insert('testCreateTable', ['bar' => 1])->execute();
        $command->insert('testCreateTable', ['bar' => 2])->execute();
        $command->insert('testCreateTable', ['bar' => 3])->execute();
        $command->insert('testCreateTable', ['bar' => 4])->execute();

        $this->assertEquals(
            4,
            $command->setSql(
                <<<SQL
                SELECT seq FROM sqlite_sequence where name='testCreateTable'
                SQL
            )->queryScalar()
        );

        $command->resetSequence('testCreateTable', 2)->execute();

        $this->assertSame(
            '1',
            $command->setSql(
                <<<SQL
                SELECT seq FROM sqlite_sequence where name='testCreateTable'
                SQL
            )->queryScalar()
        );

        $command->resetSequence('testCreateTable')->execute();

        $this->assertEquals(
            4,
            $command->setSql(
                <<<SQL
                SELECT seq FROM sqlite_sequence where name='testCreateTable'
                SQL
            )->queryScalar()
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testTruncateTable(): void
    {
        $db = $this->getConnection('customer');

        $command = $db->createCommand();
        $command->setSql(
            <<<SQL
            SELECT COUNT(*) FROM customer
            SQL
        );

        $this->assertEquals(3, $command->queryScalar());

        $command->truncateTable('customer')->execute();

        $this->assertEquals(0, $command->queryScalar());
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::update()
     */
    public function testUpdate(
        string $table,
        array $columns,
        array|string $conditions,
        array $params,
        string $expected
    ): void {
        parent::testUpdate($table, $columns, $conditions, $params, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::upsert()
     */
    public function testUpsert(array $firstData, array $secondData): void
    {
        $db = $this->getConnection('customer', 't_upsert');

        if (version_compare($db->getServerVersion(), '3.8.3', '<')) {
            $this->markTestSkipped('SQLite < 3.8.3 does not support "WITH" keyword.');
        }

        parent::testUpsert($firstData, $secondData);
    }
}
