<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Closure;
use JsonException;
use Throwable;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\Schema\ColumnSchemaBuilder;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Schema\SchemaBuilderTrait;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQueryBuilderTest;
use Yiisoft\Db\Tests\Support\Assert;
use Yiisoft\Db\Tests\Support\DbHelper;

/**
 * @group sqlite
 */
final class QueryBuilderTest extends CommonQueryBuilderTest
{
    use SchemaBuilderTrait;
    use TestTrait;

    /**
     * @throws Exception
     */
    public function testAddCheck(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.');

        $qb->addCheck('name', 'table', 'expresion');
    }

    /**
     * @throws Exception
     */
    public function testAddCommentOnColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn is not supported by SQLite.'
        );

        $qb->addCommentOnColumn('table', 'column', 'comment');
    }

    /**
     * @throws Exception
     */
    public function testAddCommentOnTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnTable is not supported by SQLite.'
        );

        $qb->addCommentOnTable('table', 'comment');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addDropChecks()
     *
     * @throws Exception
     */
    public function testAddDropCheck(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCheck is not supported by SQLite.');

        $qb->dropCheck('name', 'table');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addDropForeignKeys()
     *
     * @throws Exception
     */
    public function testAddDropForeignKey(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.'
        );

        $qb->dropForeignKey('fk', 'table');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addDropPrimaryKeys()
     *
     * @throws Exception
     */
    public function testAddDropPrimaryKey(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey is not supported by SQLite.'
        );

        $qb->dropPrimaryKey('name', 'table');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addDropUniques()
     *
     * @throws Exception
     */
    public function testAddDropUnique(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique is not supported by SQLite.'
        );

        $qb->dropUnique('name', 'table');
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function testAddForeignKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.');

        $qb->addForeignKey('test_fk', 'test_table', ['id'], 'test_table', ['id']);
    }

    /**
     * @throws Exception
     */
    public function testAddPrimaryKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey is not supported by SQLite.'
        );

        $qb->addPrimaryKey('name', 'table', 'columns');
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::alterColumn()
     *
     * @throws Exception
     */
    public function testAlterColumn(
        string $table,
        string $column,
        ColumnSchemaBuilder|string $type,
        string $expected
    ): void {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn is not supported by SQLite.'
        );

        $qb->alterColumn('table', 'column', 'type');
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::batchInsert()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBatchInsert(
        string $table,
        array $columns,
        array $value,
        string $expected = null,
        array $expectedParams = [],
    ): void {
        $db = $this->getConnection();

        $params = [];
        $sql = $db->getQueryBuilder()->batchInsert($table, $columns, $value, $params);

        $this->assertEquals($expected, $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildConditions()
     *
     * @throws Exception
     */
    public function testBuildCondition(
        array|ExpressionInterface|string $conditions,
        string $expected,
        array $expectedParams = []
    ): void {
        $db = $this->getConnection();

        $query = $this->getQuery($db)->where($conditions);

        [$sql, $params] = $db->getQueryBuilder()->build($query);

        $replacedQuotes = DbHelper::replaceQuotes($expected, $db->getName());

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildFilterConditions()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildFilterCondition(array $condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();

        $query = $this->getQuery($db)->filterWhere($condition);

        [$sql, $params] = $db->getQueryBuilder()->build($query);

        $replacedQuotes = DbHelper::replaceQuotes($expected, $db->getName());

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildFrom()
     *
     * @throws Exception
     */
    public function testBuildFrom(string $table, string $expected): void
    {
        $db = $this->getConnection();

        $params = [];
        $sql = $db->getQueryBuilder()->buildFrom([$table], $params);
        $replacedQuotes = DbHelper::replaceQuotes($expected, $db->getName());

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('FROM ' . $replacedQuotes, $sql);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildLikeConditions()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildLikeCondition(
        array|ExpressionInterface $condition,
        string $expected,
        array $expectedParams
    ): void {
        $db = $this->getConnection();

        $query = $this->getQuery($db)->where($condition);

        [$sql, $params] = $db->getQueryBuilder()->build($query);

        $replacedQuotes = DbHelper::replaceQuotes($expected, $db->getName());

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @throws Exception
     */
    public function testBuildLimitWithNull(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();
        $sql = $qb->buildLimit(null, 5);

        $this->assertSame('LIMIT 9223372036854775807 OFFSET 5', $sql);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     */
    public function testBuildOffset(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();
        $query = $this->getQuery($db)->offset(10);

        [$sql, $params] = $qb->build($query);

        $this->assertSame(
            <<<SQL
            SELECT * LIMIT 9223372036854775807 OFFSET 10
            SQL,
            $sql,
        );
        $this->assertSame([], $params);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     */
    public function testBuildUnion(): void
    {
        $db = $this->getConnection();

         $expectedQuerySql = DbHelper::replaceQuotes(
            <<<SQL
            SELECT `id` FROM `TotalExample` `t1` WHERE (w > 0) AND (x < 2) UNION  SELECT `id` FROM `TotalTotalExample` `t2` WHERE w > 5 UNION ALL  SELECT `id` FROM `TotalTotalExample` `t3` WHERE w = 3
            SQL,
            $db->getName()
        );

        $query = new Query($db);
        $secondQuery = new Query($db);
        $secondQuery->select('id')->from('TotalTotalExample t2')->where('w > 5');
        $thirdQuery = new Query($db);
        $thirdQuery->select('id')->from('TotalTotalExample t3')->where('w = 3');
        $query->select('id')
            ->from('TotalExample t1')
            ->where(['and', 'w > 0', 'x < 2'])
            ->union($secondQuery)
            ->union($thirdQuery, true);

        [$actualQuerySql, $queryParams] = $db->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals([], $queryParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildExistsParams()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildWhereExists(string $cond, string $expectedQuerySql): void
    {
        $db = $this->getConnection();

        $expectedQueryParams = [];
        $subQuery = new Query($db);
        $subQuery->select('1')->from('Website w');
        $query = new Query($db);
        $query->select('id')->from('TotalExample t')->where([$cond, $subQuery]);

        [$actualQuerySql, $actualQueryParams] = $db->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals($expectedQueryParams, $actualQueryParams);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     */
    public function testBuildWithQuery(): void
    {
        $db = $this->getConnection();

        $expectedQuerySql = DbHelper::replaceQuotes(
            <<<SQL
            WITH a1 AS (SELECT [[id]] FROM [[t1]] WHERE expr = 1), a2 AS (SELECT [[id]] FROM [[t2]] INNER JOIN [[a1]] ON t2.id = a1.id WHERE expr = 2 UNION  SELECT [[id]] FROM [[t3]] WHERE expr = 3) SELECT * FROM [[a2]]
            SQL,
            $db->getName(),
        );

        $with1Query = $this->getQuery($db)->select('id')->from('t1')->where('expr = 1');
        $with2Query = $this->getQuery($db)->select('id')->from('t2')->innerJoin('a1', 't2.id = a1.id')->where('expr = 2');
        $with3Query = $this->getQuery($db)->select('id')->from('t3')->where('expr = 3');
        $query = $this->getQuery($db)
            ->withQuery($with1Query, 'a1')
            ->withQuery($with2Query->union($with3Query), 'a2')
            ->from('a2');

        [$actualQuerySql, $queryParams] = $db->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals([], $queryParams);
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function testCheckIntegrity(): void
    {
        $db = $this->getConnection();

        $this->assertEquals('PRAGMA foreign_keys=1', $db->getQueryBuilder()->checkIntegrity());
        $this->assertEquals('PRAGMA foreign_keys=0', $db->getQueryBuilder()->checkIntegrity('', '', false));
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::createDropIndex()
     *
     * @throws Exception
     */
    public function testCreateDropIndex(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();

        $this->assertSame($db->getQuoter()->quoteSql($sql), $builder($db->getQueryBuilder()));
    }

    /**
     * @throws Exception
     */
    public function testCreateTable(): void
    {
        $this->db = $this->getConnectionWithData();

        $qb = $this->db->getQueryBuilder();

        $expected = DbHelper::replaceQuotes(
            <<<SQL
            CREATE TABLE [[test_table]] (
            \t[[id]] integer PRIMARY KEY AUTOINCREMENT NOT NULL,
            \t[[name]] varchar(255) NOT NULL,
            \t[[email]] varchar(255) NOT NULL,
            \t[[address]] varchar(255) NOT NULL,
            \t[[status]] integer NOT NULL,
            \t[[profile_id]] integer NOT NULL,
            \t[[created_at]] timestamp NOT NULL,
            \t[[updated_at]] timestamp NOT NULL
            ) CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB
            SQL,
            $this->db->getName(),
        );
        $columns = [
            'id' => $this->primaryKey(5),
            'name' => $this->string(255)->notNull(),
            'email' => $this->string(255)->notNull(),
            'address' => $this->string(255)->notNull(),
            'status' => $this->integer()->notNull(),
            'profile_id' => $this->integer()->notNull(),
            'created_at' => $this->timestamp()->notNull(),
            'updated_at' => $this->timestamp()->notNull(),
        ];
        $options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        $sql = $qb->createTable('test_table', $columns, $options);

        Assert::equalsWithoutLE($expected, $sql);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::delete()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testDelete(string $table, array|string $condition, string $expectedSQL, array $expectedParams): void
    {
        $db = $this->getConnection();

        $actualParams = [];
        $actualSQL = $db->getQueryBuilder()->delete($table, $condition, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @throws Exception
     */
    public function testDropCheck(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCheck is not supported by SQLite.'
        );

        $qb->dropCheck('name', 'table');
    }

    /**
     * @throws Exception
     */
    public function testDropColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropColumn is not supported by SQLite.'
        );

        $qb->dropColumn('table', 'column');
    }

    /**
     * @throws Exception
     */
    public function testDropCommentFromColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromColumn is not supported by SQLite.'
        );

        $qb->dropCommentFromColumn('table', 'column');
    }

    /**
     * @throws Exception
     */
    public function testsDropCommentFromTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromTable is not supported by SQLite.'
        );

        $qb->dropCommentFromTable('table');
    }

    /**
     * @throws Exception
     */
    public function testDropForeignKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.'
        );

        $qb->dropForeignKey('name', 'table');
    }

    /**
     * @throws Exception
     */
    public function testDropPrimaryKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey is not supported by SQLite.'
        );

        $qb->dropPrimaryKey('name', 'table');
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::insert()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testInsert(
        string $table,
        array|QueryInterface $columns,
        array $params,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $db = $this->getConnection();

        $this->assertSame($expectedSQL, $db->getQueryBuilder()->insert($table, $columns, $params));
        $this->assertSame($expectedParams, $params);
    }

    /**
     * @throws Exception
     */
    public function testRenameColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::renameColumn is not supported by SQLite.'
        );

        $qb->renameColumn('table', 'old_name', 'new_name');
    }

    /**
     * @throws Exception
     */
    public function testRenameTable(): void
    {
        $db = $this->getConnection();

        $sql = $db->getQueryBuilder()->renameTable('table_from', 'table_to');

        $this->assertEquals('ALTER TABLE `table_from` RENAME TO `table_to`', $sql);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     */
    public function testResetSequence(): void
    {
        $db = $this->getConnectionWithData();

        if ($db->getSchema()->getTableSchema('testCreateTable', true) !== null) {
            $db->createCommand()->dropTable('testCreateTable')->execute();
        }

        $db->createCommand()->createTable(
            'testCreateTable',
            ['id' => Schema::TYPE_PK, 'bar' => Schema::TYPE_INTEGER]
        )->execute();

        $db->createCommand()->insert('testCreateTable', ['bar' => 1])->execute();

        $qb = $db->getQueryBuilder();
        $checkSql = <<<SQL
        SELECT seq FROM sqlite_sequence where name='testCreateTable'
        SQL;

        // change to max row
        $expected = <<<SQL
        UPDATE sqlite_sequence SET seq=(SELECT MAX(`id`) FROM `testCreateTable`) WHERE name='testCreateTable'
        SQL;
        $sql = $qb->resetSequence('testCreateTable');

        $this->assertSame($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();

        $this->assertSame(1, $result);

        // change up
        $expected = <<<SQL
        UPDATE sqlite_sequence SET seq='0' WHERE name='testCreateTable'
        SQL;
        $sql = $qb->resetSequence('testCreateTable', '1');

        $this->assertSame($expected, $sql);

        $expected = <<<SQL
        UPDATE sqlite_sequence SET seq='3' WHERE name='testCreateTable'
        SQL;
        $sql = $qb->resetSequence('testCreateTable', 4);

        $this->assertSame($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();

        $this->assertSame('3', $result);

        // and again change to max rows
        $expected = <<<SQL
        UPDATE sqlite_sequence SET seq=(SELECT MAX(`id`) FROM `testCreateTable`) WHERE name='testCreateTable'
        SQL;
        $sql = $qb->resetSequence('testCreateTable');

        $this->assertSame($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();

        $this->assertSame(1, $result);
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function testResetSequenceExceptionTableNoExist(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table not found: no_table');

        $qb->resetSequence('no_table');
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function testResetSequenceExceptionInvalidSequenceName(): void
    {
        $db = $this->getConnectionWithData();

        $qb = $db->getQueryBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("There is not sequence associated with table 'order_item_with_null_fk'.");

        $qb->resetSequence('order_item_with_null_fk');
    }

    /**
     * @throws Exception
     */
    public function testTruncateTable(): void
    {
        $db = $this->getConnectionWithData();

        $qb = $db->getQueryBuilder();
        $sql = $qb->truncateTable('customer');

        $this->assertSame(
            <<<SQL
            DELETE FROM `customer`
            SQL,
            $sql
        );
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::update()
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testUpdate(
        string $table,
        array $columns,
        array|string $condition,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $db = $this->getConnection();

        $actualParams = [];
        $actualSQL = $db->getQueryBuilder()->update($table, $columns, $condition, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::upsert()
     *
     * @param string|string[] $expectedSQL
     *
     * @throws Exception
     * @throws JsonException
     * @throws NotSupportedException
     */
    public function testUpsert(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns,
        string|array $expectedSQL,
        array $expectedParams
    ): void {
        $db = $this->getConnectionWithData();

        $actualParams = [];
        $actualSQL = $db->getQueryBuilder()->upsert($table, $insertColumns, $updateColumns, $actualParams);

        if (is_string($expectedSQL)) {
            $this->assertSame($expectedSQL, $actualSQL);
        } else {
            $this->assertContains($actualSQL, $expectedSQL);
        }

        if (ArrayHelper::isAssociative($expectedParams)) {
            $this->assertSame($expectedParams, $actualParams);
        } else {
            Assert::isOneOf($actualParams, $expectedParams);
        }
    }
}
