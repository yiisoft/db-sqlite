<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\QueryBuilder;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonCommandTest;

use function version_compare;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class CommandTest extends CommonCommandTest
{
    use TestTrait;

    protected string $upsertTestCharCast = 'CAST([[address]] AS VARCHAR(255))';

    public function testAddCheck(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.');

        parent::testAddCheck();
    }

    public function testAddCommentOnColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn is not supported by SQLite.'
        );

        parent::testaddCommentOnColumn();
    }

    public function testAddCommentOnTable(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnTable is not supported by SQLite.'
        );

        parent::testAddCommentOnTable();
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function testAddDefaultValue(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder does not support adding default value constraints.'
        );

        $db->createCommand()->addDefaultValue('name', 'table', 'column', 'value')->execute();
    }

    public function testAddForeignKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.'
        );

        parent::testAddForeignKey();
    }

    public function testAddPrimaryKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey is not supported by SQLite.'
        );

        parent::testAddPrimaryKey();
    }

    public function testAddUnique(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique is not supported by SQLite.');

        parent::testAddUnique();
    }

    public function testAlterColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn is not supported by SQLite.');

        parent::testAlterColumn();
    }

    public function testAlterTable(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn is not supported by SQLite.');

        parent::testAlterTable();
    }

    public function testDropCheck(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCheck is not supported by SQLite.');

        parent::testDropCheck();
    }

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

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAutoQuoting(): void
    {
        $db = $this->getConnection();

        $sql = <<<SQL
        SELECT [[id]], [[t.name]] FROM {{customer}} t
        SQL;
        $command = $db->createCommand($sql);

        $this->assertSame(
            <<<SQL
            SELECT `id`, `t`.`name` FROM `customer` t
            SQL,
            $command->getSql(),
        );
    }

    /**
     * Make sure that `{{something}}` in values will not be encoded.
     *
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::batchInsertSql()
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     *
     * {@see https://github.com/yiisoft/yii2/issues/11242}
     */
    public function testBatchInsertSQL(
        string $table,
        array $columns,
        array $values,
        string $expected,
        array $expectedParams = [],
        int $insertedRow = 1
    ): void {
        parent::testBatchInsertSQL($table, $columns, $values, $expected, $expectedParams, $insertedRow);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testBindParamValue(): void
    {
        parent::testBindParamValue();

        $db = $this->getConnectionWithData();

        $command = $db->createCommand();
        $command = $command->setSql(
            <<<SQL
            INSERT INTO type (int_col, char_col, float_col, blob_col, numeric_col, bool_col) VALUES (:int_col, :char_col, :float_col, :blob_col, :numeric_col, :bool_col)
            SQL
        );
        $intCol = 123;
        $charCol = 'abc';
        $floatCol = 1.23;
        $blobCol = "\x10\x11\x12";
        $numericCol = '1.23';
        $boolCol = false;
        $command->bindParam(':int_col', $intCol);
        $command->bindParam(':char_col', $charCol);
        $command->bindParam(':float_col', $floatCol);
        $command->bindParam(':blob_col', $blobCol);
        $command->bindParam(':numeric_col', $numericCol);
        $command->bindParam(':bool_col', $boolCol);

        $this->assertEquals(1, $command->execute());

        $row = $command->setSql(
            <<<SQL
            SELECT int_col, char_col, float_col, blob_col AS blob_col, numeric_col FROM type
            SQL
        )->queryOne();

        $this->assertIsArray($row);
        $this->assertEquals($intCol, $row['int_col']);
        $this->assertSame($charCol, trim($row['char_col']));
        $this->assertEquals($floatCol, (float) $row['float_col']);
        $this->assertSame($blobCol, $row['blob_col']);
        $this->assertEquals($numericCol, $row['numeric_col']);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testCheckIntegrity(): void
    {
        $db = $this->getConnection();

        $this->assertSame(
            'PRAGMA foreign_keys=1',
            $db->createCommand()->checkIntegrity('', '')->getSql(),
        );
    }

    public function testCreateDropIndex(): void
    {
        $db = $this->getConnection();

        $tableName = 'test_idx';
        $name = 'test_idx_constraint';

        $schema = $db->getSchema();

        if ($schema->getTableSchema($tableName) !== null) {
            $db->createCommand()->dropTable($tableName)->execute();
        }

        $db->createCommand()->createTable($tableName, [
            'int1' => 'integer not null',
            'int2' => 'integer not null',
        ])->execute();
        $this->assertEmpty($schema->getTableIndexes($tableName, true));

        $db->createCommand()->createIndex($name, $tableName, ['int1'])->execute();
        $this->assertEquals(['int1'], $schema->getTableIndexes($tableName, true)[0]->getColumnNames());
        $this->assertFalse($schema->getTableIndexes($tableName, true)[0]->isUnique());

        $db->createCommand()->dropIndex($name, $tableName)->execute();
        $this->assertEmpty($schema->getTableIndexes($tableName, true));

        $db->createCommand()->createIndex($name, $tableName, ['int1', 'int2'])->execute();
        $this->assertEquals(['int1', 'int2'], $schema->getTableIndexes($tableName, true)[0]->getColumnNames());
        $this->assertFalse($schema->getTableIndexes($tableName, true)[0]->isUnique());

        $db->createCommand()->dropIndex($name, $tableName)->execute();
        $this->assertEmpty($schema->getTableIndexes($tableName, true));
        $this->assertEmpty($schema->getTableIndexes($tableName, true));

        $db->createCommand()->createIndex($name, $tableName, ['int1'], QueryBuilder::INDEX_UNIQUE)->execute();
        $this->assertEquals(['int1'], $schema->getTableIndexes($tableName, true)[0]->getColumnNames());
        $this->assertTrue($schema->getTableIndexes($tableName, true)[0]->isUnique());

        $db->createCommand()->dropIndex($name, $tableName)->execute();
        $this->assertEmpty($schema->getTableIndexes($tableName, true));

        $db->createCommand()->createIndex($name, $tableName, ['int1', 'int2'], QueryBuilder::INDEX_UNIQUE)->execute();
        $this->assertEquals(['int1', 'int2'], $schema->getTableIndexes($tableName, true)[0]->getColumnNames());
        $this->assertTrue($schema->getTableIndexes($tableName, true)[0]->isUnique());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropIndex(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $sql = $command->dropIndex('name', 'table')->getSql();

        $this->assertSame(
            <<<SQL
            DROP INDEX `name`
            SQL,
            $sql
        );
    }

    public function testDropColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropColumn is not supported by SQLite.');

        parent::testDropColumn();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropCommentFromColumn(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromColumn is not supported by SQLite.'
        );

        $command->dropCommentFromColumn('table', 'column');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropCommentFromTable(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromTable is not supported by SQLite.'
        );

        $command->dropCommentFromTable('table');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testDropDefaultValue(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder does not support dropping default value constraints.'
        );

        $db->createCommand()->dropDefaultValue('customer', 'name')->execute();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testDropForeingKey(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.'
        );

        $db->createCommand()->dropForeignKey('departments', 'test_fk_constraint')->execute();
    }

    public function testDropPrimaryKey(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey is not supported by SQLite.'
        );

        parent::testDropPrimaryKey();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testDropUnique(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique is not supported by SQLite.'
        );

        $db->createCommand()->dropUnique('departments', 'test_fk_constraint')->execute();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
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

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testExecuteResetSequence(): void
    {
        $db = $this->getConnection();

        if ($db->getSchema()->getTableSchema('testCreateTable', true) !== null) {
            $db->createCommand()->dropTable('testCreateTable')->execute();
        }

        $db->createCommand()->createTable(
            'testCreateTable',
            ['id' => Schema::TYPE_PK, 'bar' => Schema::TYPE_INTEGER]
        )->execute();

        $db->createCommand()->insert('testCreateTable', ['bar' => 1])->execute();
        $db->createCommand()->insert('testCreateTable', ['bar' => 2])->execute();
        $db->createCommand()->insert('testCreateTable', ['bar' => 3])->execute();
        $db->createCommand()->insert('testCreateTable', ['bar' => 4])->execute();

        $this->assertEquals(
            4,
            $db->createCommand(
                "SELECT seq FROM sqlite_sequence where name='testCreateTable'"
            )->queryScalar()
        );

        $db->createCommand()->resetSequence('testCreateTable', 2)->execute();

        $this->assertSame(
            '1',
            $db->createCommand(
                <<<SQL
                SELECT seq FROM sqlite_sequence where name='testCreateTable'
                SQL
            )->queryScalar()
        );

        $db->createCommand()->resetSequence('testCreateTable')->execute();

        $this->assertEquals(
            4,
            $db->createCommand(
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
    public function testMultiStatementSupport(): void
    {
        $db = $this->getConnectionWithData();

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

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testRenameColumn(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::renameColumn is not supported by SQLite.'
        );

        $db->createCommand()->renameColumn('customer', 'name', 'name_new')->execute();
    }

    public function testRenameTable(): void
    {
        $db = $this->getConnectionWithData();

        $db->createCommand()->renameTable('customer', 'customer_new')->execute();

        $this->assertSame(
            'customer_new',
            $db->getSchema()->getTableSchema('customer_new')?->getName(),
        );
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function testResetSequence(): void
    {
        $db = $this->getConnection();

        if ($db->getSchema()->getTableSchema('testCreateTable', true) !== null) {
            $db->createCommand()->dropTable('testCreateTable')->execute();
        }

        $db->createCommand()->createTable(
            'testCreateTable',
            ['id' => Schema::TYPE_PK, 'bar' => Schema::TYPE_INTEGER]
        )->execute();

        $db->createCommand()->insert('testCreateTable', ['bar' => 1])->execute();

        $db->createCommand()->resetSequence('testCreateTable', 2)->execute();

        $this->assertSame(
            '1',
            $db->createCommand(
                <<<SQL
                SELECT seq FROM sqlite_sequence where name='testCreateTable'
                SQL
            )->queryScalar()
        );
    }

    public function testTruncateTable(): void
    {
        $db = $this->getConnectionWithData();

        $sql = $db->createCommand()->truncateTable('customer')->getSql();

        $this->assertSame(
            <<<SQL
            DELETE FROM `customer`
            SQL,
            $sql
        );
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::upsert()
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testUpsert(array $firstData, array $secondData): void
    {
        if (version_compare($this->getConnection()->getServerVersion(), '3.8.3', '<')) {
            $this->markTestSkipped('SQLite < 3.8.3 does not support "WITH" keyword.');
        }

        parent::testUpsert($firstData, $secondData);
    }
}
