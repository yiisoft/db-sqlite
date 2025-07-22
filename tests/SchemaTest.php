<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use JsonException;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Constant\ReferentialAction;
use Yiisoft\Db\Constraint\Check;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Schema\Column\ColumnInterface;
use Yiisoft\Db\Sqlite\Schema;
use Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonSchemaTest;
use Yiisoft\Db\Tests\Support\DbHelper;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class SchemaTest extends CommonSchemaTest
{
    use TestTrait;

    public function testColumnComment(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn is not supported by SQLite.'
        );

        parent::testColumnComment();
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::columns
     */
    public function testColumns(array $columns, string $tableName): void
    {
        parent::testColumns($columns, $tableName);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::columnsTypeBit
     */
    public function testColumnWithTypeBit(array $columns): void
    {
        $this->assertTableColumns($columns, 'type_bit');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testCompositeFk(): void
    {
        $db = $this->getConnection(true);

        $schema = $db->getSchema();
        $table = $schema->getTableSchema('composite_fk');

        $this->assertNotNull($table);

        $fk = $table->getForeignKeys();

        $this->assertCount(1, $fk);
        $this->assertTrue(isset($fk[0]));
        $this->assertEquals('order_item', $fk[0][0]);
        $this->assertEquals('order_id', $fk[0]['order_id']);
        $this->assertEquals('item_id', $fk[0]['item_id']);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testForeignKey(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $schema = $db->getSchema();
        $command->setSql(
            <<<SQL
            PRAGMA foreign_keys = ON
            SQL
        )->execute();
        $tableMaster = 'departments';
        $tableRelation = 'students';
        $tableRelation1 = 'benefits';

        if ($schema->getTableSchema($tableRelation1) !== null) {
            $command->dropTable($tableRelation1)->execute();
        }

        if ($schema->getTableSchema($tableRelation) !== null) {
            $command->dropTable($tableRelation)->execute();
        }

        if ($schema->getTableSchema($tableMaster) !== null) {
            $command->dropTable($tableMaster)->execute();
        }

        $command->createTable(
            $tableMaster,
            [
                'id' => 'integer not null primary key autoincrement',
                'name' => 'nvarchar(50) null',
            ],
        )->execute();
        $foreignKeys = $schema->getTableForeignKeys($tableMaster);

        $this->assertSame([], $foreignKeys);

        $command->createTable(
            $tableRelation,
            [
                'id' => 'integer primary key autoincrement not null',
                'name' => 'nvarchar(50) null',
                'department_id' => 'integer not null',
                'dateOfBirth' => 'date null',
                'CONSTRAINT fk_departments FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE',
            ],
        )->execute();

        $foreignKeys = $schema->getTableForeignKeys($tableRelation);
        $expectedForeignKeys = [
            new ForeignKey(
                '0',
                ['department_id'],
                '',
                $tableMaster,
                ['id'],
                ReferentialAction::CASCADE,
                ReferentialAction::NO_ACTION,
            ),
        ];

        $this->assertEquals($expectedForeignKeys, $foreignKeys);

        $command->createTable(
            $tableRelation1,
            [
                'id' => 'integer primary key autoincrement not null',
                'benefit' => 'nvarchar(50) null',
                'student_id' => 'integer not null',
                'department_id' => 'integer not null',
                'CONSTRAINT fk_students FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE',
                'CONSTRAINT fk_departments FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE',
            ],
        )->execute();

        $foreignKeys = $schema->getTableForeignKeys($tableRelation1);
        $expectedForeignKeys[] = new ForeignKey(
            '1',
            ['student_id'],
            '',
            $tableRelation,
            ['id'],
            ReferentialAction::CASCADE,
            ReferentialAction::NO_ACTION,
        );

        $this->assertEquals($expectedForeignKeys, $foreignKeys);
    }

    public function testMultiForeingKeys(): void
    {
        $db = $this->getConnection(true);
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema('foreign_keys_child');

        $this->assertNotNull($tableSchema);

        $foreignKeys = $tableSchema->getForeignKeys();

        $this->assertSame(
            [
                [
                    'foreign_keys_parent',
                    'y' => 'b',
                    'z' => 'c',
                ],
                [
                    'foreign_keys_parent',
                    'x' => 'a',
                    'y' => 'b',
                ],
            ],
            $foreignKeys
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetTableChecks(): void
    {
        $db = $this->getConnection(true);

        $schema = $db->getSchema();
        $tableChecks = $schema->getTableChecks('T_constraints_check');

        $this->assertIsArray($tableChecks);
        $this->assertContainsOnlyInstancesOf(Check::class, $tableChecks);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetSchemaDefaultValues(): void
    {
        $db = $this->getConnection();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\Schema::getSchemaDefaultValues is not supported by SQLite.');

        $db->getSchema()->getSchemaDefaultValues();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetSchemaNames(): void
    {
        $db = $this->getConnection();

        $schema = $db->getSchema();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Schema does not support fetching all schema names.'
        );

        $schema->getSchemaNames();
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetTableDefaultValues(): void
    {
        $db = $this->getConnection();

        $schema = $db->getSchema();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('SQLite does not support default value constraints.');

        $schema->getTableDefaultValues('customer');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetTableForeignKeys(): void
    {
        $db = $this->getConnection(true);

        $schema = $db->getSchema();
        $tableForeignKeys = $schema->getTableForeignKeys('T_constraints_3');

        $this->assertEquals(
            [new ForeignKey(
                '0',
                [ 'C_fk_id_1', 'C_fk_id_2'],
                '',
                'T_constraints_2',
                ['C_id_1', 'C_id_2'],
                ReferentialAction::CASCADE,
                ReferentialAction::CASCADE,
            )],
            $tableForeignKeys
        );

        $tableTwoForeignKeys = $schema->getTableForeignKeys('foreign_keys_child');
        $this->assertCount(2, $tableTwoForeignKeys);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints
     *
     * @throws Exception
     * @throws JsonException
     */
    public function testTableSchemaConstraints(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraints($tableName, $type, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws JsonException
     * @throws NotSupportedException
     */
    public function testTableSchemaConstraintsWithPdoLowercase(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraintsWithPdoLowercase($tableName, $type, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints
     *
     * @throws Exception
     * @throws JsonException
     */
    public function testTableSchemaConstraintsWithPdoUppercase(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraintsWithPdoUppercase($tableName, $type, $expected);
    }

    public function testWorkWithUniqueConstraint(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique is not supported by SQLite.'
        );

        parent::testWorkWithUniqueConstraint();
    }

    public function testWorkWithCheckConstraint(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.'
        );

        parent::testWorkWithCheckConstraint();
    }

    public function testWorkWithDefaultValueConstraint(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addDefaultValue is not supported by SQLite.'
        );

        parent::testWorkWithDefaultValueConstraint();
    }

    public function testWorkWithPrimaryKeyConstraint(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey is not supported by SQLite.'
        );

        parent::testWorkWithPrimaryKeyConstraint();
    }

    public function testNotConnectionPDO(): void
    {
        $db = $this->createMock(ConnectionInterface::class);
        $schema = new Schema($db, DbHelper::getSchemaCache());

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Only PDO connections are supported.');

        $schema->refresh();
    }

    #[DataProviderExternal(SchemaProvider::class, 'resultColumns')]
    public function testGetResultColumn(ColumnInterface|null $expected, array $info): void
    {
        parent::testGetResultColumn($expected, $info);
    }
}
