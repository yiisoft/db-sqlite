<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Closure;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\QueryBuilder;
use Yiisoft\Db\TestUtility\TestQueryBuilderTrait;
use Yiisoft\Db\TestUtility\TraversableObject;

/**
 * @group sqlite
 */
final class QueryBuilderTest extends TestCase
{
    use TestQueryBuilderTrait;

    protected string $likeEscapeCharSql = " ESCAPE '\\'";

    /**
     * @return QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->getConnection());
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

        $secondQuery->select('id')
            ->from('TotalTotalExample t2')
            ->where('w > 5');

        $thirdQuery = new Query($db);

        $thirdQuery->select('id')
            ->from('TotalTotalExample t3')
            ->where('w = 3');
        $query->select('id')
            ->from('TotalExample t1')
            ->where(['and', 'w > 0', 'x < 2'])
            ->union($secondQuery)
            ->union($thirdQuery, true);
        [$actualQuerySql, $queryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals([], $queryParams);
    }

    public function testBuildWithQuery()
    {
        $db = $this->getConnection();

        $expectedQuerySql = $this->replaceQuotes(
            'WITH a1 AS (SELECT [[id]] FROM [[t1]] WHERE expr = 1), a2 AS (SELECT [[id]] FROM [[t2]]'
            . ' INNER JOIN [[a1]] ON t2.id = a1.id WHERE expr = 2 UNION  SELECT [[id]] FROM [[t3]] WHERE expr = 3)'
            . ' SELECT * FROM [[a2]]'
        );

        $with1Query = (new Query($db))
            ->select('id')
            ->from('t1')
            ->where('expr = 1');

        $with2Query = (new Query($db))
            ->select('id')
            ->from('t2')
            ->innerJoin('a1', 't2.id = a1.id')
            ->where('expr = 2');

        $with3Query = (new Query($db))
            ->select('id')
            ->from('t3')
            ->where('expr = 3');

        $query = (new Query($db))
            ->withQuery($with1Query, 'a1')
            ->withQuery($with2Query->union($with3Query), 'a2')
            ->from('a2');

        [$actualQuerySql, $queryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals([], $queryParams);
    }

    public function testRenameTable()
    {
        $sql = $this->getQueryBuilder()->renameTable('table_from', 'table_to');
        $this->assertEquals('ALTER TABLE `table_from` RENAME TO `table_to`', $sql);
    }

    public function testResetSequence(): void
    {
        $qb = $this->getQueryBuilder(true, true);

        $expected = "UPDATE sqlite_sequence SET seq='5' WHERE name='item'";
        $sql = $qb->resetSequence('item');
        $this->assertEquals($expected, $sql);

        $expected = "UPDATE sqlite_sequence SET seq='3' WHERE name='item'";
        $sql = $qb->resetSequence('item', 4);
        $this->assertEquals($expected, $sql);
    }

    public function addDropForeignKeysProvider(): array
    {
        $tableName = 'T_constraints_3';
        $name = 'CN_constraints_3';
        $pkTableName = 'T_constraints_2';

        return [
            'drop' => [
                'PRAGMA foreign_keys = 0;SAVEPOINT drop_column_T_constraints_3;CREATE TABLE `temp_T_constraints_3` AS SELECT * FROM `T_constraints_3`;DROP TABLE `T_constraints_3`;CREATE TABLE `T_constraints_3` ("C_id" INT NOT NULL,' . " \n"
                . '"C_fk_id_1" INT NOT NULL,' . " \n"
                . '"C_fk_id_2" INT NOT NULL);INSERT INTO `T_constraints_3` SELECT "C_id","C_fk_id_1","C_fk_id_2" FROM `temp_T_constraints_3`;DROP TABLE `temp_T_constraints_3`;RELEASE drop_column_T_constraints_3;PRAGMA foreign_keys = 0',
                static function (QueryBuilder $qb) use ($tableName, $name) {
                    return $qb->dropForeignKey($name, $tableName);
                },
            ],
            'add' => [
                'PRAGMA foreign_keys = off;SAVEPOINT add_foreign_key_to_temp_T_constraints_3;CREATE TEMP TABLE'
                . ' `temp_T_constraints_3` AS SELECT * FROM `T_constraints_3`;DROP TABLE `T_constraints_3`;'
                . 'CREATE TABLE `T_constraints_3` ("C_id" INT NOT NULL,' . "\n"
                . '    "C_fk_id_1" INT NOT NULL,' . "\n"
                . '    "C_fk_id_2" INT NOT NULL,' . "\n"
                . '    CONSTRAINT "CN_constraints_3" FOREIGN KEY ("C_fk_id_1", "C_fk_id_2") REFERENCES'
                . ' "T_constraints_2" ("C_id_1", "C_id_2") ON DELETE CASCADE ON UPDATE CASCADE,' . "\n"
                . 'CONSTRAINT `CN_constraints_3` FOREIGN KEY (C_fk_id_1) REFERENCES T_constraints_2(C_id_1)'
                . ' ON UPDATE CASCADE ON DELETE CASCADE);INSERT INTO `T_constraints_3`'
                . ' SELECT * FROM `temp_T_constraints_3`;DROP TABLE `temp_T_constraints_3`;'
                . 'RELEASE add_foreign_key_to_temp_T_constraints_3;PRAGMA foreign_keys = 0',
                static function (QueryBuilder $qb) use ($tableName, $name, $pkTableName) {
                    return $qb->addForeignKey(
                        $name,
                        $tableName,
                        'C_fk_id_1',
                        $pkTableName,
                        'C_id_1',
                        'CASCADE',
                        'CASCADE'
                    );
                },
            ],
            'add (2 columns)' => [
                'PRAGMA foreign_keys = off;SAVEPOINT add_foreign_key_to_temp_T_constraints_3;'
                . 'CREATE TEMP TABLE `temp_T_constraints_3` AS SELECT * FROM `T_constraints_3`;'
                . 'DROP TABLE `T_constraints_3`;CREATE TABLE `T_constraints_3` ("C_id" INT NOT NULL,' . "\n"
                . '    "C_fk_id_1" INT NOT NULL,' . "\n"
                . '    "C_fk_id_2" INT NOT NULL,' . "\n"
                . '    CONSTRAINT "CN_constraints_3" FOREIGN KEY ("C_fk_id_1", "C_fk_id_2")'
                . ' REFERENCES "T_constraints_2" ("C_id_1", "C_id_2") ON DELETE CASCADE ON UPDATE CASCADE,' . "\n"
                . 'CONSTRAINT `CN_constraints_3` FOREIGN KEY (C_fk_id_1, C_fk_id_2)'
                . ' REFERENCES T_constraints_2(C_id_1, C_id_2) ON UPDATE CASCADE ON DELETE CASCADE);'
                . 'INSERT INTO `T_constraints_3` SELECT * FROM `temp_T_constraints_3`;'
                . 'DROP TABLE `temp_T_constraints_3`;RELEASE add_foreign_key_to_temp_T_constraints_3;'
                . 'PRAGMA foreign_keys = 0',
                static function (QueryBuilder $qb) use ($tableName, $name, $pkTableName) {
                    return $qb->addForeignKey(
                        $name,
                        $tableName,
                        'C_fk_id_1, C_fk_id_2',
                        $pkTableName,
                        'C_id_1, C_id_2',
                        'CASCADE',
                        'CASCADE'
                    );
                },
            ],
        ];
    }

    /**
     * @dataProvider addDropForeignKeysProvider
     *
     * @param string $sql
     * @param Closure $builder
     */
    public function testAddDropForeignKey(string $sql, Closure $builder): void
    {
        $this->assertEqualsWithoutLE($this->getConnection()->quoteSql($sql), $builder($this->getQueryBuilder()));
    }

    public function batchInsertProvider(): array
    {
        $data = $this->batchInsertProviderTrait();

        $data['escape-danger-chars']['expected'] = 'INSERT INTO `customer` (`address`)'
            . " VALUES ('SQL-danger chars are escaped: ''); --')";

        return $data;
    }

    /**
     * @dataProvider batchInsertProvider
     *
     * @param string $table
     * @param array $columns
     * @param array $value
     * @param string $expected
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBatchInsert(string $table, array $columns, array $value, string $expected): void
    {
        $queryBuilder = $this->getQueryBuilder();

        $sql = $queryBuilder->batchInsert($table, $columns, $value);

        $this->assertEquals($expected, $sql);
    }

    public function buildConditionsProvider(): array
    {
        return array_merge($this->buildConditionsProviderTrait(), [
            'composite in using array objects' => [
                ['in', new TraversableObject(['id', 'name']), new TraversableObject([
                    ['id' => 1, 'name' => 'oy'],
                    ['id' => 2, 'name' => 'yo'],
                ])],
                '(([[id]] = :qp0 AND [[name]] = :qp1) OR ([[id]] = :qp2 AND [[name]] = :qp3))',
                [':qp0' => 1, ':qp1' => 'oy', ':qp2' => 2, ':qp3' => 'yo'],
            ],
            'composite in' => [
                ['in', ['id', 'name'], [['id' => 1, 'name' => 'oy']]],
                '(([[id]] = :qp0 AND [[name]] = :qp1))',
                [':qp0' => 1, ':qp1' => 'oy'],
            ],
        ]);
    }

    /**
     * @dataProvider buildConditionsProvider
     *
     * @param array|ExpressionInterface $condition
     * @param string $expected
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildCondition($condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();

        $query = (new Query($db))->where($condition);

        [$sql, $params] = $this->getQueryBuilder()->build($query);

        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $this->replaceQuotes($expected)), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider buildFilterConditionProviderTrait
     *
     * @param array $condition
     * @param string $expected
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildFilterCondition(array $condition, string $expected, array $expectedParams): void
    {
        $query = (new Query($this->getConnection()))->filterWhere($condition);

        [$sql, $params] = $this->getQueryBuilder()->build($query);

        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $this->replaceQuotes($expected)), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    public function indexesProvider(): array
    {
        $result = parent::indexesProvider();
        $result['drop'][0] = 'DROP INDEX [[CN_constraints_2_single]]';

        $indexName = 'myindex';
        $schemaName = 'myschema';
        $tableName = 'mytable';

        $result['with schema'] = [
            "CREATE INDEX {{{$schemaName}}}.[[$indexName]] ON {{{$tableName}}} ([[C_index_1]])",
            function (QueryBuilder $qb) use ($tableName, $indexName, $schemaName) {
                return $qb->createIndex($indexName, $schemaName . '.' . $tableName, 'C_index_1');
            },
        ];

        return $result;
    }

    /**
     * @dataProvider buildFromDataProviderTrait
     *
     * @param string $table
     * @param string $expected
     *
     * @throws Exception
     */
    public function testBuildFrom(string $table, string $expected): void
    {
        $params = [];

        $sql = $this->getQueryBuilder()->buildFrom([$table], $params);

        $this->assertEquals('FROM ' . $this->replaceQuotes($expected), $sql);
    }

    /**
     * @dataProvider buildLikeConditionsProviderTrait
     *
     * @param array|object $condition
     * @param string $expected
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testBuildLikeCondition($condition, string $expected, array $expectedParams): void
    {
        $db = $this->getConnection();

        $query = (new Query($db))->where($condition);

        [$sql, $params] = $this->getQueryBuilder()->build($query);

        $this->assertEquals('SELECT *' . (empty($expected) ? '' : ' WHERE ' . $this->replaceQuotes($expected)), $sql);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @dataProvider buildExistsParamsProviderTrait
     *
     * @param string $cond
     * @param string $expectedQuerySql
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

        $subQuery->select('1')
            ->from('Website w');

        $query = new Query($db);

        $query->select('id')
            ->from('TotalExample t')
            ->where([$cond, $subQuery]);

        [$actualQuerySql, $actualQueryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals($expectedQueryParams, $actualQueryParams);
    }

    public function createDropIndexesProvider(): array
    {
        $result = $this->createDropIndexesProviderTrait();

        $result['drop'][0] = 'DROP INDEX [[CN_constraints_2_single]]';

        $indexName = 'myindex';
        $schemaName = 'myschema';
        $tableName = 'mytable';

        $result['with schema'] = [
            "CREATE INDEX {{{$schemaName}}}.[[$indexName]] ON {{{$tableName}}} ([[C_index_1]])",
            function (QueryBuilder $qb) use ($tableName, $indexName, $schemaName) {
                return $qb->createIndex($indexName, $schemaName . '.' . $tableName, 'C_index_1');
            },
        ];

        return $result;
    }

    /**
     * @dataProvider createDropIndexesProvider
     *
     * @param string $sql
     */
    public function testCreateDropIndex(string $sql, Closure $builder): void
    {
        $this->assertSame($this->getConnection()->quoteSql($sql), $builder($this->getQueryBuilder()));
    }

    /**
     * @dataProvider deleteProviderTrait
     *
     * @param string $table
     * @param array|string $condition
     * @param string $expectedSQL
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testDelete(string $table, $condition, string $expectedSQL, array $expectedParams): void
    {
        $actualParams = [];

        $actualSQL = $this->getQueryBuilder()->delete($table, $condition, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @dataProvider insertProviderTrait
     *
     * @param string $table
     * @param array|ColumnSchema $columns
     * @param array $params
     * @param string $expectedSQL
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testInsert(string $table, $columns, array $params, string $expectedSQL, array $expectedParams): void
    {
        $actualParams = $params;

        $actualSQL = $this->getQueryBuilder()->insert($table, $columns, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @dataProvider updateProviderTrait
     *
     * @param string $table
     * @param array $columns
     * @param array|string $condition
     * @param string $expectedSQL
     * @param array $expectedParams
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testUpdate(
        string $table,
        array $columns,
        $condition,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $actualParams = [];

        $actualSQL = $this->getQueryBuilder()->update($table, $columns, $condition, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);
        $this->assertSame($expectedParams, $actualParams);
    }

    public function upsertProvider(): array
    {
        $concreteData = [
            'regular values' => [
                3 => 'WITH "EXCLUDED" (`email`, `address`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=(SELECT `address` FROM `EXCLUDED`), `status`=(SELECT `status` FROM `EXCLUDED`), `profile_id`=(SELECT `profile_id` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);',
            ],
            'regular values with update part' => [
                3 => 'WITH "EXCLUDED" (`email`, `address`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=:qp4, `status`=:qp5, `orders`=T_upsert.orders + 1 WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);',
            ],
            'regular values without update part' => [
                3 => 'INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3)',
            ],
            'query' => [
                3 => 'WITH "EXCLUDED" (`email`, `status`) AS (SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1) UPDATE `T_upsert` SET `status`=(SELECT `status` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1;',
            ],
            'query with update part' => [
                3 => 'WITH "EXCLUDED" (`email`, `status`) AS (SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1) UPDATE `T_upsert` SET `address`=:qp1, `status`=:qp2, `orders`=T_upsert.orders + 1 WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1;',
            ],
            'query without update part' => [
                3 => 'INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1',
            ],
            'values and expressions' => [
                3 => 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())',
            ],
            'values and expressions with update part' => [
                3 => 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())',
            ],
            'values and expressions without update part' => [
                3 => 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())',
            ],
            'query, values and expressions with update part' => [
                3 => 'WITH "EXCLUDED" (`email`, [[time]]) AS (SELECT :phEmail AS `email`, now() AS [[time]]) UPDATE {{%T_upsert}} SET `ts`=:qp1, [[orders]]=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[time]]) SELECT :phEmail AS `email`, now() AS [[time]];',
            ],
            'query, values and expressions without update part' => [
                3 => 'WITH "EXCLUDED" (`email`, [[time]]) AS (SELECT :phEmail AS `email`, now() AS [[time]]) UPDATE {{%T_upsert}} SET `ts`=:qp1, [[orders]]=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[time]]) SELECT :phEmail AS `email`, now() AS [[time]];',
            ],
            'no columns to update' => [
                3 => 'INSERT OR IGNORE INTO `T_upsert_1` (`a`) VALUES (:qp0)',
            ],
        ];

        $newData = $this->upsertProviderTrait();

        foreach ($concreteData as $testName => $data) {
            $newData[$testName] = array_replace($newData[$testName], $data);
        }

        return $newData;
    }

    /**
     * @depends testInitFixtures
     *
     * @dataProvider upsertProvider
     *
     * @param string $table
     * @param array|ColumnSchema $insertColumns
     * @param array|bool|null $updateColumns
     * @param string|string[] $expectedSQL
     * @param array $expectedParams
     *
     * @throws NotSupportedException
     * @throws Exception
     */
    public function testUpsert(string $table, $insertColumns, $updateColumns, $expectedSQL, array $expectedParams): void
    {
        $actualParams = [];

        $actualSQL = $this->getQueryBuilder()
            ->upsert($table, $insertColumns, $updateColumns, $actualParams);

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
