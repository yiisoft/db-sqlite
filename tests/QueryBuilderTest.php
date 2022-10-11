<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Closure;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\Sqlite\Schema;
use Yiisoft\Db\TestSupport\TestQueryBuilderTrait;

/**
 * @group sqlite
 */
final class QueryBuilderTest extends TestCase
{
    use TestQueryBuilderTrait;

    public function testAddForeignKey(): void
    {
        $qb = $this->getConnection()->getQueryBuilder();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.');
        $qb->addForeignKey('test_fk', 'test_table', ['id'], 'test_table', ['id']);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::batchInsertProvider
     *
     * @param string $expected
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testBatchInsert(string $table, array $columns, array $value, ?string $expected, array $expectedParams = []): void
    {
        $params = [];
        $db = $this->getConnection();

        $sql = $db->getQueryBuilder()->batchInsert($table, $columns, $value, $params);

        $this->assertEquals($expected, $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildConditionsProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testBuildCondition(array|ExpressionInterface|string $condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();
        $query = (new Query($db))->where($condition);
        [$sql, $params] = $db->getQueryBuilder()->build($query);
        $replacedQuotes = $this->replaceQuotes($expected);

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildFilterConditionProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testBuildFilterCondition(array $condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();
        $query = (new Query($db))->filterWhere($condition);
        [$sql, $params] = $db->getQueryBuilder()->build($query);
        $replacedQuotes = $this->replaceQuotes($expected);

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildFromDataProvider
     *
     * @throws Exception
     */
    public function testBuildFrom(string $table, string $expected): void
    {
        $db = $this->getConnection();
        $params = [];
        $sql = $db->getQueryBuilder()->buildFrom([$table], $params);
        $replacedQuotes = $this->replaceQuotes($expected);

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('FROM ' . $replacedQuotes, $sql);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildLikeConditionsProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testBuildLikeCondition(array|ExpressionInterface $condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();
        $query = (new Query($db))->where($condition);
        [$sql, $params] = $db->getQueryBuilder()->build($query);
        $replacedQuotes = $this->replaceQuotes($expected);

        $this->assertIsString($replacedQuotes);
        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $replacedQuotes), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    public function testBuildUnion(): void
    {
        $db = $this->getConnection();

        $expectedQuerySql = $this->replaceQuotes(
            'SELECT `id` FROM `TotalExample` `t1` WHERE (w > 0) AND (x < 2) UNION  SELECT `id`'
            . ' FROM `TotalTotalExample` `t2` WHERE w > 5 UNION ALL  SELECT `id`'
            . ' FROM `TotalTotalExample` `t3` WHERE w = 3'
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
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildExistsParamsProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
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

    public function testBuildWithQuery()
    {
        $db = $this->getConnection();

        $expectedQuerySql = $this->replaceQuotes(
            'WITH a1 AS (SELECT [[id]] FROM [[t1]] WHERE expr = 1), a2 AS (SELECT [[id]] FROM [[t2]]'
            . ' INNER JOIN [[a1]] ON t2.id = a1.id WHERE expr = 2 UNION  SELECT [[id]] FROM [[t3]] WHERE expr = 3)'
            . ' SELECT * FROM [[a2]]'
        );
        $with1Query = (new Query($db))->select('id')->from('t1')->where('expr = 1');
        $with2Query = (new Query($db))->select('id')->from('t2')->innerJoin('a1', 't2.id = a1.id')->where('expr = 2');
        $with3Query = (new Query($db))->select('id')->from('t3')->where('expr = 3');
        $query = (new Query($db))
            ->withQuery($with1Query, 'a1')
            ->withQuery($with2Query->union($with3Query), 'a2')
            ->from('a2');

        [$actualQuerySql, $queryParams] = $db->getQueryBuilder()->build($query);
        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals([], $queryParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::createDropIndexesProvider
     */
    public function testCreateDropIndex(string $sql, Closure $builder): void
    {
        $db = $this->getConnection();
        $this->assertSame($db->getQuoter()->quoteSql($sql), $builder($db->getQueryBuilder()));
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::deleteProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testDelete(string $table, array|string $condition, string $expectedSQL, array $expectedParams): void
    {
        $db = $this->getConnection();
        $actualParams = [];
        $actualSQL = $db->getQueryBuilder()->delete($table, $condition, $actualParams);
        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    public function testCreateTableColumnTypes(): void
    {
        $db = $this->getConnection(true);
        $qb = $db->getQueryBuilder();

        if ($db->getTableSchema('column_type_table', true) !== null) {
            $db->createCommand($qb->dropTable('column_type_table'))->execute();
        }

        $columns = [];
        $i = 0;

        foreach ($this->columnTypes() as [$column, $builder, $expected]) {
            if (
                !(
                    strncmp($column, Schema::TYPE_PK, 2) === 0 ||
                    strncmp($column, Schema::TYPE_UPK, 3) === 0 ||
                    strncmp($column, Schema::TYPE_BIGPK, 5) === 0 ||
                    strncmp($column, Schema::TYPE_UBIGPK, 6) === 0 ||
                    str_starts_with(substr($column, -5), 'FIRST')
                )
            ) {
                $columns['col' . ++$i] = str_replace('CHECK (value', 'CHECK ([[col' . $i . ']]', $column);
            }
        }

        $db->createCommand($qb->createTable('column_type_table', $columns))->execute();
        $this->assertNotEmpty($db->getTableSchema('column_type_table', true));
    }

    public function testDropForeignKey(): void
    {
        $qb = $this->getConnection()->getQueryBuilder();
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.');
        $qb->dropForeignKey('test_fk', 'test_table');
    }

    public function testRenameTable()
    {
        $db = $this->getConnection();
        $sql = $db->getQueryBuilder()->renameTable('table_from', 'table_to');
        $this->assertEquals('ALTER TABLE `table_from` RENAME TO `table_to`', $sql);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::insertProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testInsert(string $table, array|QueryInterface $columns, array $params, string $expectedSQL, array $expectedParams): void
    {
        $db = $this->getConnection();
        $this->assertSame($expectedSQL, $db->getQueryBuilder()->insert($table, $columns, $params));
        $this->assertSame($expectedParams, $params);
    }

    public function testResetSequence(): void
    {
        $db = $this->getConnection(true);
        $qb = $db->getQueryBuilder();

        $checkSql = "SELECT seq FROM sqlite_sequence where name='testCreateTable'";

        // change to max row
        $expected = "UPDATE sqlite_sequence SET seq=(SELECT MAX(`id`) FROM `testCreateTable`) WHERE name='testCreateTable'";
        $sql = $qb->resetSequence('testCreateTable');
        $this->assertEquals($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();
        $this->assertEquals(1, $result);

        // change up
        $expected = "UPDATE sqlite_sequence SET seq='3' WHERE name='testCreateTable'";
        $sql = $qb->resetSequence('testCreateTable', 4);
        $this->assertEquals($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();
        $this->assertEquals(3, $result);

        // and again change to max rows
        $expected = "UPDATE sqlite_sequence SET seq=(SELECT MAX(`id`) FROM `testCreateTable`) WHERE name='testCreateTable'";
        $sql = $qb->resetSequence('testCreateTable');
        $this->assertEquals($expected, $sql);

        $db->createCommand($sql)->execute();
        $result = $db->createCommand($checkSql)->queryScalar();
        $this->assertEquals(1, $result);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::updateProvider
     *
     * @throws Exception|InvalidArgumentException|InvalidConfigException|NotSupportedException
     */
    public function testUpdate(
        string $table,
        array $columns,
        array|string $condition,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $actualParams = [];
        $db = $this->getConnection();
        $actualSQL = $db->getQueryBuilder()->update($table, $columns, $condition, $actualParams);
        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::upsertProvider
     *
     * @param string|string[] $expectedSQL
     *
     * @throws Exception|NotSupportedException
     */
    public function testUpsert(string $table, array|QueryInterface $insertColumns, array|bool $updateColumns, string|array $expectedSQL, array $expectedParams): void
    {
        $actualParams = [];
        $db = $this->getConnection();
        $actualSQL = $db->getQueryBuilder()->upsert($table, $insertColumns, $updateColumns, $actualParams);

        if (is_string($expectedSQL)) {
            $this->assertSame($expectedSQL, $actualSQL);
        } else {
            $this->assertContains($actualSQL, $expectedSQL);
        }

        if (ArrayHelper::isAssociative($expectedParams)) {
            $this->assertSame($expectedParams, $actualParams);
        } else {
            $this->assertIsOneOf($actualParams, $expectedParams);
        }
    }
}
