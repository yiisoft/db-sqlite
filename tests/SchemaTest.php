<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use PDO;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Schema\TableSchema;
use Yiisoft\Db\TestUtility\AnyValue;
use Yiisoft\Db\TestUtility\TestSchemaTrait;

/**
 * @group sqlite
 */
final class SchemaTest extends TestCase
{
    use TestSchemaTrait;

    public function getExpectedColumns()
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

    public function testCompositeFk()
    {
        $db = $this->getConnection();

        $schema = $db->getSchema();

        $table = $schema->getTableSchema('composite_fk');

        $fk = $table->getForeignKeys();
        $this->assertCount(1, $fk);
        $this->assertTrue(isset($fk[0]));
        $this->assertEquals('order_item', $fk[0][0]);
        $this->assertEquals('order_id', $fk[0]['order_id']);
        $this->assertEquals('item_id', $fk[0]['item_id']);
    }

    /**
     * @dataProvider pdoAttributesProviderTrait
     *
     * @param array $pdoAttributes
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testGetTableNames(array $pdoAttributes): void
    {
        $connection = $this->getConnection(true);

        foreach ($pdoAttributes as $name => $value) {
            $connection->getPDO()->setAttribute($name, $value);
        }

        $schema = $connection->getSchema();

        $tables = $schema->getTableNames();

        if ($connection->getDriverName() === 'sqlsrv') {
            $tables = array_map(static function ($item) {
                return trim($item, '[]');
            }, $tables);
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
     * @dataProvider pdoAttributesProviderTrait
     *
     * @param array $pdoAttributes
     */
    public function testGetTableSchemas(array $pdoAttributes): void
    {
        $db = $this->getConnection(true);

        foreach ($pdoAttributes as $name => $value) {
            $db->getPDO()->setAttribute($name, $value);
        }

        $schema = $db->getSchema();

        $tables = $schema->getTableSchemas();

        $this->assertCount(count($schema->getTableNames()), $tables);

        foreach ($tables as $table) {
            $this->assertInstanceOf(TableSchema::class, $table);
        }
    }

    public function quoteTableNameDataProvider(): array
    {
        return [
            ['test', '`test`'],
            ['test.test', '`test`.`test`'],
            ['test.test.test', '`test`.`test`.`test`'],
            ['`test`', '`test`'],
            ['`test`.`test`', '`test`.`test`'],
            ['test.`test`.test', '`test`.`test`.`test`'],
        ];
    }

    /**
     * @dataProvider quoteTableNameDataProvider
     *
     * @param $name
     * @param $expectedName
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testQuoteTableName($name, $expectedName): void
    {
        $schema = $this->getConnection()->getSchema();
        $quotedName = $schema->quoteTableName($name);
        $this->assertEquals($expectedName, $quotedName);
    }

    public function constraintsProvider()
    {
        $result = $this->constraintsProviderTrait();

        $result['1: primary key'][2]->name(null);
        $result['1: check'][2][0]->columnNames(null);
        $result['1: check'][2][0]->expression('"C_check" <> \'\'');
        $result['1: unique'][2][0]->name(AnyValue::getInstance());
        $result['1: index'][2][1]->name(AnyValue::getInstance());

        $result['2: primary key'][2]->name(null);
        $result['2: unique'][2][0]->name(AnyValue::getInstance());
        $result['2: index'][2][2]->name(AnyValue::getInstance());

        $result['3: foreign key'][2][0]->name(null);
        $result['3: index'][2] = [];

        $result['4: primary key'][2]->name(null);
        $result['4: unique'][2][0]->name(AnyValue::getInstance());

        return $result;
    }

    /**
     * @dataProvider constraintsProvider
     *
     * @param string $tableName
     * @param string $type
     * @param mixed $expected
     */
    public function testTableSchemaConstraints(string $tableName, string $type, $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $constraints = $this->getConnection()->getSchema()->{'getTable' . ucfirst($type)}($tableName);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @dataProvider lowercaseConstraintsProviderTrait
     *
     * @param string $tableName
     * @param string $type
     * @param mixed $expected
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoLowercase(string $tableName, string $type, $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $connection = $this->getConnection();

        $connection->getSlavePdo()->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);

        $constraints = $connection->getSchema()->{'getTable' . ucfirst($type)}($tableName, true);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @dataProvider uppercaseConstraintsProviderTrait
     *
     * @param string $tableName
     * @param string $type
     * @param mixed $expected
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTableSchemaConstraintsWithPdoUppercase(string $tableName, string $type, $expected): void
    {
        if ($expected === false) {
            $this->expectException(NotSupportedException::class);
        }

        $connection = $this->getConnection();

        $connection->getSlavePdo()->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

        $constraints = $connection->getSchema()->{'getTable' . ucfirst($type)}($tableName, true);

        $this->assertMetadataEquals($expected, $constraints);
    }

    /**
     * @depends testSchemaCache
     *
     * @dataProvider tableSchemaCachePrefixesProviderTrait
     *
     * @param string $tablePrefix
     * @param string $tableName
     * @param string $testTablePrefix
     * @param string $testTableName
     */
    public function testTableSchemaCacheWithTablePrefixes(
        string $tablePrefix,
        string $tableName,
        string $testTablePrefix,
        string $testTableName
    ): void {
        $db = $this->getConnection();
        $schema = $this->getConnection()->getSchema();

        $this->schemaCache->setEnable(true);

        $db->setTablePrefix($tablePrefix);

        $noCacheTable = $schema->getTableSchema($tableName, true);

        $this->assertInstanceOf(TableSchema::class, $noCacheTable);

        /* Compare */
        $db->setTablePrefix($testTablePrefix);

        $testNoCacheTable = $schema->getTableSchema($testTableName);

        $this->assertSame($noCacheTable, $testNoCacheTable);

        $db->setTablePrefix($tablePrefix);

        $schema->refreshTableSchema($tableName);

        $refreshedTable = $schema->getTableSchema($tableName, false);

        $this->assertInstanceOf(TableSchema::class, $refreshedTable);
        $this->assertNotSame($noCacheTable, $refreshedTable);

        /* Compare */
        $db->setTablePrefix($testTablePrefix);

        $schema->refreshTableSchema($testTablePrefix);

        $testRefreshedTable = $schema->getTableSchema($testTableName, false);

        $this->assertInstanceOf(TableSchema::class, $testRefreshedTable);
        $this->assertEquals($refreshedTable, $testRefreshedTable);
        $this->assertNotSame($testNoCacheTable, $testRefreshedTable);
    }

    public function testsCountIndexUnique(): void
    {
        $db = $this->getConnection();

        $tableName = 'test_uq';
        $name = 'test_uq_constraint';

        $schema = $db->getSchema();

        if ($schema->getTableSchema($tableName) !== null) {
            $db->createCommand()->dropTable($tableName)->execute();
        }

        $db->createCommand()->createTable($tableName, [
            'int1' => 'integer not null',
            'int2' => 'integer not null',
        ])->execute();

        $this->assertEmpty($schema->getTableIndexes($tableName, true));
        $this->assertEmpty($schema->getTableUniques($tableName, true));

        $db->createCommand()->addUnique($name, $tableName, ['int1'])->execute();

        $this->assertCount(1, $schema->getTableIndexes($tableName, true));
        $this->assertCount(1, $schema->getTableUniques($tableName, true));
        $this->assertEquals(['int1'], $schema->getTableUniques($tableName, true)[0]->getColumnNames());

        $db->createCommand()->dropUnique($name, $tableName)->execute();

        $this->assertEmpty($schema->getTableIndexes($tableName, true));
        $this->assertEmpty($schema->getTableUniques($tableName, true));

        $db->createCommand()->addUnique($name, $tableName, ['int1', 'int2'])->execute();

        $this->assertCount(1, $schema->getTableIndexes($tableName, true));
        $this->assertCount(1, $schema->getTableUniques($tableName, true));
        $this->assertEquals(['int1', 'int2'], $schema->getTableUniques($tableName, true)[0]->getColumnNames());
    }
}
