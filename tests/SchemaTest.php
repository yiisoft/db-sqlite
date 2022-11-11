<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\QueryBuilder\QueryBuilder;
use Yiisoft\Db\Schema\TableSchemaInterface;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonSchemaTest;

use function array_map;
use function trim;
use function ucfirst;

/**
 * @group sqlite
 */
final class SchemaTest extends CommonSchemaTest
{
    use TestTrait;

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
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::pdoAttributes()
     *
     * @throws Exception
     */
    public function testGetTableNames(array $pdoAttributes): void
    {
        $db = $this->getConnectionWithData();

        foreach ($pdoAttributes as $name => $value) {
            $db->getPDO()?->setAttribute($name, $value);
        }

        $schema = $db->getSchema();
        $tables = $schema->getTableNames();

        if ($db->getDriver()->getDriverName() === 'sqlsrv') {
            $tables = array_map(static fn ($item) => trim($item, '[]'), $tables);
        }

        $this->assertContains('customer', $tables);
        $this->assertContains('category', $tables);
        $this->assertContains('item', $tables);
        $this->assertContains('order', $tables);
        $this->assertContains('order_item', $tables);
        $this->assertContains('type', $tables);
        $this->assertContains('animal', $tables);
        $this->assertContains('animal_view', $tables);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::pdoAttributes()
     *
     * @throws Exception
     */
    public function testGetTableSchemas(array $pdoAttributes): void
    {
        $db = $this->getConnectionWithData();

        foreach ($pdoAttributes as $name => $value) {
            $db->getPDO()?->setAttribute($name, $value);
        }

        $schema = $db->getSchema();
        $tables = $schema->getTableSchemas();

        $this->assertCount(count($schema->getTableNames()), $tables);

        foreach ($tables as $table) {
            $this->assertInstanceOf(TableSchemaInterface::class, $table);
        }
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::quoteTableName()
     *
     * @throws Exception
     */
    public function testQuoteTableName(string $name, string $expectedName): void
    {
        $db = $this->getConnection();

        $quoter = $db->getQuoter();
        $quotedName = $quoter->quoteTableName($name);

        $this->assertSame($expectedName, $quotedName);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::quoterTableParts()
     *
     * @throws Exception
     */
    public function testQuoterTableParts(string $tableName, ...$expectedParts): void
    {
        $quoter = $this->getConnection()->getQuoter();

        $parts = $quoter->getTableNameParts($tableName);

        $this->assertEquals($expectedParts, array_reverse($parts));
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     */
    public function testTableSchemaConstraints(string $tableName, string $type, mixed $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $db = $this->getConnectionWithData();
        $schema = $db->getSchema();
        $constraints = $schema->{'getTable' . ucfirst($type)}($tableName);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoLowercase(string $tableName, string $type, mixed $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();
        $db->getActivePDO()->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $constraints = $schema->{'getTable' . ucfirst($type)}($tableName, true);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::constraints()
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoUppercase(string $tableName, string $type, mixed $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();
        $db->getActivePDO()->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);
        $constraints = $schema->{'getTable' . ucfirst($type)}($tableName, true);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\SchemaProvider::tableSchemaCachePrefixes()
     *
     * @throws Exception
     */
    public function testTableSchemaCacheWithTablePrefixes(
        string $tablePrefix,
        string $tableName,
        string $testTablePrefix,
        string $testTableName
    ): void {
        $db = $this->getConnectionWithData();

        $schema = $db->getSchema();
        $schemaCache = $this->getSchemaCache();

        $this->assertNotNull($schemaCache);

        $schema->schemaCacheEnable(true);
        $db->setTablePrefix($tablePrefix);
        $noCacheTable = $schema->getTableSchema($tableName, true);

        $this->assertInstanceOf(TableSchemaInterface::class, $noCacheTable);

        /* Compare */
        $db->setTablePrefix($testTablePrefix);
        $testNoCacheTable = $schema->getTableSchema($testTableName);

        $this->assertSame($noCacheTable, $testNoCacheTable);

        $db->setTablePrefix($tablePrefix);
        $schema->refreshTableSchema($tableName);
        $refreshedTable = $schema->getTableSchema($tableName);

        $this->assertInstanceOf(TableSchemaInterface::class, $refreshedTable);
        $this->assertNotSame($noCacheTable, $refreshedTable);

        /* Compare */
        $db->setTablePrefix($testTablePrefix);
        $schema->refreshTableSchema($testTablePrefix);
        $testRefreshedTable = $schema->getTableSchema($testTableName);

        $this->assertInstanceOf(TableSchemaInterface::class, $testRefreshedTable);
        $this->assertEquals($refreshedTable, $testRefreshedTable);
        $this->assertNotSame($testNoCacheTable, $testRefreshedTable);
    }

    protected function getExpectedColumns(): array
    {
        return [
            'int_col' => [
                'type' => 'integer',
                'dbType' => 'integer',
                'phpType' => 'integer',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => null,
            ],
            'int_col2' => [
                'type' => 'integer',
                'dbType' => 'integer',
                'phpType' => 'integer',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => 1,
            ],
            'tinyint_col' => [
                'type' => 'tinyint',
                'dbType' => 'tinyint(3)',
                'phpType' => 'integer',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 3,
                'precision' => 3,
                'scale' => null,
                'defaultValue' => 1,
            ],
            'smallint_col' => [
                'type' => 'smallint',
                'dbType' => 'smallint(1)',
                'phpType' => 'integer',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 1,
                'precision' => 1,
                'scale' => null,
                'defaultValue' => 1,
            ],
            'char_col' => [
                'type' => 'char',
                'dbType' => 'char(100)',
                'phpType' => 'string',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 100,
                'precision' => 100,
                'scale' => null,
                'defaultValue' => null,
            ],
            'char_col2' => [
                'type' => 'string',
                'dbType' => 'varchar(100)',
                'phpType' => 'string',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 100,
                'precision' => 100,
                'scale' => null,
                'defaultValue' => 'something',
            ],
            'char_col3' => [
                'type' => 'text',
                'dbType' => 'text',
                'phpType' => 'string',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => null,
            ],
            'float_col' => [
                'type' => 'double',
                'dbType' => 'double(4,3)',
                'phpType' => 'double',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 4,
                'precision' => 4,
                'scale' => 3,
                'defaultValue' => null,
            ],
            'float_col2' => [
                'type' => 'double',
                'dbType' => 'double',
                'phpType' => 'double',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => 1.23,
            ],
            'blob_col' => [
                'type' => 'binary',
                'dbType' => 'blob',
                'phpType' => 'resource',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => null,
            ],
            'numeric_col' => [
                'type' => 'decimal',
                'dbType' => 'decimal(5,2)',
                'phpType' => 'string',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 5,
                'precision' => 5,
                'scale' => 2,
                'defaultValue' => '33.22',
            ],
            'time' => [
                'type' => 'timestamp',
                'dbType' => 'timestamp',
                'phpType' => 'string',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => '2002-01-01 00:00:00',
            ],
            'bool_col' => [
                'type' => 'boolean',
                'dbType' => 'tinyint(1)',
                'phpType' => 'boolean',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 1,
                'precision' => 1,
                'scale' => null,
                'defaultValue' => null,
            ],
            'bool_col2' => [
                'type' => 'boolean',
                'dbType' => 'tinyint(1)',
                'phpType' => 'boolean',
                'allowNull' => true,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => 1,
                'precision' => 1,
                'scale' => null,
                'defaultValue' => true,
            ],
            'ts_default' => [
                'type' => 'timestamp',
                'dbType' => 'timestamp',
                'phpType' => 'string',
                'allowNull' => false,
                'autoIncrement' => false,
                'enumValues' => null,
                'size' => null,
                'precision' => null,
                'scale' => null,
                'defaultValue' => new Expression('CURRENT_TIMESTAMP'),
            ],
        ];
    }
}
