<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\QueryBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonSchemaTest;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class SchemaTest extends CommonSchemaTest
{
    use TestTrait;

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::columns()
     */
    public function testColumnSchema(array $columns): void
    {
        parent::testColumnSchema($columns);
    }

    /**
     * @throws Exception
     */
    public function testCompositeFk(): void
    {
        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();
        $table = $schema->getTableSchema('composite_fk');

        $this->assertNotNull($table);

        $fk = $table->getForeignKeys();

        $this->assertCount(1, $fk);
        $this->assertTrue(isset($fk[0]));
        $this->assertSame('order_item', $fk[0][0]);
        $this->assertSame('order_id', $fk[0]['order_id']);
        $this->assertSame('item_id', $fk[0]['item_id']);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testFindUniqueIndexes(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $schema = $db->getSchema();

        try {
            $command->dropTable('uniqueIndex')->execute();
        } catch (Exception) {
        }

        $command->createTable('uniqueIndex', ['somecol' => 'string', 'someCol2' => 'string'])->execute();
        $tableSchema = $schema->getTableSchema('uniqueIndex', true);

        $this->assertNotNull($tableSchema);

        $uniqueIndexes = $schema->findUniqueIndexes($tableSchema);

        $this->assertSame([], $uniqueIndexes);

        $command->createIndex('somecolUnique', 'uniqueIndex', 'somecol', QueryBuilder::INDEX_UNIQUE)->execute();
        $tableSchema = $schema->getTableSchema('uniqueIndex', true);

        $this->assertNotNull($tableSchema);

        $uniqueIndexes = $schema->findUniqueIndexes($tableSchema);

        $this->assertSame(['somecolUnique' => ['somecol']], $uniqueIndexes);

        /* Create another column with upper case letter that fails postgres @link https://github.com/yiisoft/yii2/issues/10613 */
        $command->createIndex('someCol2Unique', 'uniqueIndex', 'someCol2', QueryBuilder::INDEX_UNIQUE)->execute();
        $tableSchema = $schema->getTableSchema('uniqueIndex', true);

        $this->assertNotNull($tableSchema);

        $uniqueIndexes = $schema->findUniqueIndexes($tableSchema);

        $this->assertSame(['someCol2Unique' => ['someCol2'], 'somecolUnique' => ['somecol']], $uniqueIndexes);

        /* see https://github.com/yiisoft/yii2/issues/13814 */
        $command->createIndex('another unique index', 'uniqueIndex', 'someCol2', QueryBuilder::INDEX_UNIQUE)->execute();
        $tableSchema = $schema->getTableSchema('uniqueIndex', true);

        $this->assertNotNull($tableSchema);

        $uniqueIndexes = $schema->findUniqueIndexes($tableSchema);

        $this->assertSame(
            ['another unique index' => ['someCol2'], 'someCol2Unique' => ['someCol2'], 'somecolUnique' => ['somecol']],
            $uniqueIndexes,
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function testForeingKey(): void
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
        $foreingKeys = $schema->getTableForeignKeys($tableMaster);

        $this->assertCount(0, $foreingKeys);
        $this->assertSame([], $foreingKeys);

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
        $foreingKeys = $schema->getTableForeignKeys($tableRelation);

        $this->assertCount(1, $foreingKeys);
        $this->assertSame(['department_id'], $foreingKeys[0]->getColumnNames());
        $this->assertSame($tableMaster, $foreingKeys[0]->getForeignTableName());
        $this->assertSame(['id'], $foreingKeys[0]->getForeignColumnNames());
        $this->assertSame('CASCADE', $foreingKeys[0]->getOnDelete());
        $this->assertSame('NO ACTION', $foreingKeys[0]->getOnUpdate());

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
        $foreingKeys = $schema->getTableForeignKeys($tableRelation1);

        $this->assertCount(2, $foreingKeys);
        $this->assertSame(['department_id'], $foreingKeys[0]->getColumnNames());
        $this->assertSame($tableMaster, $foreingKeys[0]->getForeignTableName());
        $this->assertSame(['id'], $foreingKeys[0]->getForeignColumnNames());
        $this->assertSame('CASCADE', $foreingKeys[0]->getOnDelete());
        $this->assertSame('NO ACTION', $foreingKeys[0]->getOnUpdate());
        $this->assertSame(['student_id'], $foreingKeys[1]->getColumnNames());
        $this->assertSame($tableRelation, $foreingKeys[1]->getForeignTableName());
        $this->assertSame(['id'], $foreingKeys[1]->getForeignColumnNames());
        $this->assertSame('CASCADE', $foreingKeys[1]->getOnDelete());
        $this->assertSame('NO ACTION', $foreingKeys[1]->getOnUpdate());
    }

    /**
     * @throws Exception
     */
    public function testGetLastInsertID(): void
    {
        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();

        $this->assertSame('2', $schema->getLastInsertID('customer_id_seq'));
    }

    /**
     * @throws Exception
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
     */
    public function testGetTableForeignKeys(): void
    {
        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();
        $tableForeingKeys = $schema->getTableForeignKeys('T_constraints_3');

        $this->assertCount(1, $tableForeingKeys);
        $this->assertSame([ 'C_fk_id_1', 'C_fk_id_2'], $tableForeingKeys[0]->getColumnNames());
        $this->assertSame('T_constraints_2', $tableForeingKeys[0]->getForeignTableName());
        $this->assertSame(['C_id_1', 'C_id_2'], $tableForeingKeys[0]->getForeignColumnNames());
        $this->assertSame('CASCADE', $tableForeingKeys[0]->getOnDelete());
        $this->assertSame('CASCADE', $tableForeingKeys[0]->getOnUpdate());
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     */
    public function testTableSchemaConstraints(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraints($tableName, $type, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoLowercase(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraintsWithPdoLowercase($tableName, $type, $expected);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoUppercase(string $tableName, string $type, mixed $expected): void
    {
        parent::testTableSchemaConstraintsWithPdoUppercase($tableName, $type, $expected);
    }
}
