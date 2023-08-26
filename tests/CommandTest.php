<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Schema\SchemaInterface;
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
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.'
        );

        parent::testAddCheck();
    }

    /**
     * @throws Exception
     * @throws \Exception
     * @throws InvalidConfigException
     */
    public function testAddCommentOnColumn(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn is not supported by SQLite.'
        );

        $command->addCommentOnColumn('{{customer}}', '{{name}}', 'some comment');
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
     * @throws InvalidConfigException
     */
    public function testAddDefaultValue(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addDefaultValue is not supported by SQLite.'
        );

        $command->addDefaultValue('{{table}}', '{{name}}', 'column', 'value');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\CommandProvider::addForeignKey
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testAddForeignKey(
        string $name,
        string $tableName,
        array|string $column1,
        array|string $column2,
        string $expectedName,
    ): void {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.');

        parent::testAddForeignKey($name, $tableName, $column1, $column2, $expectedName);
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\CommandProvider::addPrimaryKey
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testAddPrimaryKey(string $name, string $tableName, array|string $column): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey is not supported by SQLite.');

        parent::testAddPrimaryKey($name, $tableName, $column);
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\CommandProvider::addUnique
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testAddUnique(string $name, string $tableName, array|string $column): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique is not supported by SQLite.');

        parent::testAddUnique($name, $tableName, $column);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testAlterColumn(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn is not supported by SQLite.');

        $command->alterColumn('{{customer}}', 'email', 'ntext')->execute();
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::batchInsert
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testBatchInsert(
        string $table,
        array $columns,
        array $values,
        string $expected,
        array $expectedParams = [],
        int $insertedRow = 1
    ): void {
        parent::testBatchInsert($table, $columns, $values, $expected, $expectedParams, $insertedRow);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testCheckIntegrity(): void
    {
        $db = $this->getConnection(true);

        $command = $db->createCommand();
        $command->checkIntegrity('', '{{customer}}');

        $this->assertSame(
            <<<SQL
            PRAGMA foreign_keys=1
            SQL,
            $command->getSql()
        );
        $this->assertSame(1, $command->execute());
    }

    public function testDropCheck(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.');

        parent::testDropCheck();
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

        $command->dropCommentFromColumn('{{table}}', 'column');
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

        $command->dropCommentFromTable('{{table}}');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropDefaultValue(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropDefaultValue is not supported by SQLite.'
        );

        $command->dropDefaultValue('{{table}}', '{{name}}');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropForeignKey(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.'
        );

        $command->dropForeignKey('{{table}}', '{{name}}');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropPrimaryKey(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'iisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey is not supported by SQLite.'
        );

        $command->dropPrimaryKey('{{table}}', '{{name}}');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropUnique(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique is not supported by SQLite.'
        );

        $command->dropUnique('{{table}}', '{{name}}');
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::rawSql
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testGetRawSql(string $sql, array $params, string $expectedRawSql): void
    {
        parent::testGetRawSql($sql, $params, $expectedRawSql);
    }

    /**
     * @throws Throwable
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function testMultiStatementSupport(): void
    {
        $db = $this->getConnection();

        $sql = <<<SQL
        DROP TABLE IF EXISTS [[T_multistatement]];
        CREATE TABLE [[T_multistatement]] (
            [[intcol]] INTEGER,
            [[textcol]] TEXT
        );
        INSERT INTO [[T_multistatement]] VALUES(41, :val1);
        INSERT INTO [[T_multistatement]] VALUES(42, :val2);
        SQL;

        // check
        $db->createCommand($sql, ['val1' => 'foo', 'val2' => 'bar'])->execute();
        $queryAll = $db->createCommand(
            <<<SQL
            SELECT * FROM [[T_multistatement]]
            SQL
        )->queryAll();

        $this->assertEquals(
            [['intcol' => 41, 'textcol' => 'foo'], [ 'intcol' => 42, 'textcol' => 'bar']],
            $queryAll,
        );

        $sql = <<<SQL
        UPDATE [[T_multistatement]] SET [[intcol]] = :newInt WHERE [[textcol]] = :val1;
        DELETE FROM [[T_multistatement]] WHERE [[textcol]] = :val2;
        SELECT * FROM [[T_multistatement]]
        SQL;

        $queryAll = $db->createCommand($sql, ['newInt' => 410, 'val1' => 'foo', 'val2' => 'bar'])->queryAll();

        $this->assertEquals([['intcol' => 410, 'textcol' => 'foo']], $queryAll);
    }

    public function testRenameColumn(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::renameColumn is not supported by SQLite.'
        );

        parent::testRenameColumn();
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

        if ($db->getSchema()->getTableSchema('{{testCreateTable}}', true) !== null) {
            $command->dropTable('{{testCreateTable}}')->execute();
        }

        $command->createTable(
            '{{testCreateTable}}',
            ['id' => SchemaInterface::TYPE_PK, 'bar' => SchemaInterface::TYPE_INTEGER],
        )->execute();

        $command->insert('{{testCreateTable}}', ['bar' => 1])->execute();
        $command->insert('{{testCreateTable}}', ['bar' => 2])->execute();
        $command->insert('{{testCreateTable}}', ['bar' => 3])->execute();
        $command->insert('{{testCreateTable}}', ['bar' => 4])->execute();

        $this->assertEquals(
            4,
            $command->setSql(
                <<<SQL
                SELECT [[seq]] FROM [[sqlite_sequence]] where [[name]] = 'testCreateTable'
                SQL
            )->queryScalar()
        );

        $command->resetSequence('{{testCreateTable}}', 2)->execute();

        $this->assertSame(
            '1',
            $command->setSql(
                <<<SQL
                SELECT [[seq]] FROM [[sqlite_sequence]] where [[name]] = 'testCreateTable'
                SQL
            )->queryScalar()
        );

        $command->resetSequence('{{testCreateTable}}')->execute();

        $this->assertEquals(
            4,
            $command->setSql(
                <<<SQL
                SELECT [[seq]] FROM [[sqlite_sequence]] where [[name]] = 'testCreateTable'
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
        $db = $this->getConnection(true);

        $command = $db->createCommand();
        $command->setSql(
            <<<SQL
            SELECT COUNT(*) FROM [[customer]]
            SQL
        );

        $this->assertEquals(3, $command->queryScalar());

        $command->truncateTable('{{customer}}')->execute();

        $this->assertEquals(0, $command->queryScalar());
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::update
     *
     * @throws Exception
     * @throws Throwable
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
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\CommandProvider::upsert
     *
     * @throws Exception
     * @throws Throwable
     */
    public function testUpsert(array $firstData, array $secondData): void
    {
        $db = $this->getConnection();

        if (version_compare($db->getServerVersion(), '3.8.3', '<')) {
            $this->markTestSkipped('SQLite < 3.8.3 does not support "WITH" keyword.');
        }

        parent::testUpsert($firstData, $secondData);
    }

    public function testShowDatabases(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();

        $this->assertSame('sqlite::memory:', $db->getDriver()->getDsn());
        $this->assertSame(['main'], $command->showDatabases());
    }

    public function testJsonTable(): void
    {
        $db = $this->getConnection();
        $command = $db->createCommand();

        if ($db->getTableSchema('json_table', true) !== null) {
            $command->dropTable('json_table')->execute();
        }

        $command->createTable('json_table', [
            'id' => SchemaInterface::TYPE_PK,
            'json_col' => SchemaInterface::TYPE_JSON,
        ])->execute();

        $command->insert('json_table', ['id' => 1, 'json_col' => ['a' => 1, 'b' => 2]])->execute();
        $command->insert('json_table', ['id' => 2, 'json_col' => new JsonExpression(['c' => 3, 'd' => 4])])->execute();

        $tableSchema = $db->getTableSchema('json_table', true);
        $this->assertNotNull($tableSchema);
        $this->assertSame('json_col', $tableSchema->getColumn('json_col')->getName());
        $this->assertSame('json', $tableSchema->getColumn('json_col')->getType());
        $this->assertSame('json', $tableSchema->getColumn('json_col')->getDbType());

        $this->assertSame(
            '{"a":1,"b":2}',
            $command->setSql('SELECT `json_col` FROM `json_table` WHERE `id`=1')->queryScalar(),
        );

        $this->assertSame(
            '{"c":3,"d":4}',
            $command->setSql('SELECT `json_col` FROM `json_table` WHERE `id`=2')->queryScalar(),
        );
    }
}
