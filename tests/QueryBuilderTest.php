<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use JsonException;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Column;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Common\CommonQueryBuilderTest;

/**
 * @group sqlite
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class QueryBuilderTest extends CommonQueryBuilderTest
{
    use TestTrait;

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddcheck(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addCheck is not supported by SQLite.');

        $qb->addCheck('id', 'customer', 'id > 0');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddCommentOnColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnColumn is not supported by SQLite.'
        );

        $qb->addCommentOnColumn('customer', 'id', 'Primary key.');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddCommentOnTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::addCommentOnTable is not supported by SQLite.'
        );

        $qb->addCommentOnTable('customer', 'Customer table.');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddDefaultValue(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addDefaultValue is not supported by SQLite.');

        $qb->addDefaultValue('T_constraints_1', 'CN_pk', 'C_default', 1);
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addForeignKey
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddForeignKey(
        string $name,
        string $table,
        array|string $columns,
        string $refTable,
        array|string $refColumns,
        string|null $delete,
        string|null $update,
        string $expected
    ): void {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addForeignKey is not supported by SQLite.');

        $qb->addForeignKey($table, $name, $columns, $refTable, $refColumns, $delete, $update);
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addPrimaryKey
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddPrimaryKey(string $name, string $table, array|string $columns, string $expected): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addPrimaryKey is not supported by SQLite.');

        $qb->addPrimaryKey($table, $name, $columns);
    }

    /**
     * @dataProvider \Yiisoft\Db\Tests\Provider\QueryBuilderProvider::addUnique
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAddUnique(string $name, string $table, array|string $columns, string $expected): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::addUnique is not supported by SQLite.');

        $qb->addUnique($table, $name, $columns);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testAlterColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::alterColumn is not supported by SQLite.');

        $qb->alterColumn('customer', 'email', SchemaInterface::TYPE_STRING);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::batchInsert
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Throwable
     */
    public function testBatchInsert(
        string $table,
        array $columns,
        iterable $rows,
        string $expected,
        array $expectedParams = [],
    ): void {
        parent::testBatchInsert($table, $columns, $rows, $expected, $expectedParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildCondition
     */
    public function testBuildCondition(
        array|ExpressionInterface|string $condition,
        string|null $expected,
        array $expectedParams
    ): void {
        parent::testBuildCondition($condition, $expected, $expectedParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildLikeCondition
     */
    public function testBuildLikeCondition(
        array|ExpressionInterface $condition,
        string $expected,
        array $expectedParams
    ): void {
        parent::testBuildLikeCondition($condition, $expected, $expectedParams);
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

        $qb = $db->getQueryBuilder();
        $query = (new Query($db))->from('admin_user')->union((new Query($db))->from('admin_profile'));
        $params = [];

        $this->assertSame(
            <<<SQL
            UNION  SELECT * FROM `admin_profile`
            SQL,
            $qb->buildUnion($query->getUnions(), $params),
        );
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildFrom
     */
    public function testBuildWithFrom(mixed $table, string $expectedSql, array $expectedParams = []): void
    {
        parent::testBuildWithFrom($table, $expectedSql, $expectedParams);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     */
    public function testBuildwithOffset(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();
        $query = (new Query($db))->offset(10);

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
    public function testBuildWithQuery(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();
        $with1Query = (new query($db))->select('id')->from('t1')->where('expr = 1');
        $with2Query = (new query($db))->select('id')->from('t2')->innerJoin('a1', 't2.id = a1.id')->where('expr = 2');
        $with3Query = (new query($db))->select('id')->from('t3')->where('expr = 3');
        $query = (new query($db))
            ->withQuery($with1Query, 'a1')
            ->withQuery($with2Query->union($with3Query), 'a2')
            ->from('a2');

        [$sql, $queryParams] = $qb->build($query);

        $this->assertSame(
            <<<SQL
            WITH `a1` AS (SELECT `id` FROM `t1` WHERE expr = 1), `a2` AS (SELECT `id` FROM `t2` INNER JOIN `a1` ON t2.id = a1.id WHERE expr = 2 UNION  SELECT `id` FROM `t3` WHERE expr = 3) SELECT * FROM `a2`
            SQL,
            $sql,
        );
        $this->assertSame([], $queryParams);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     */
    public function testBuildWithUnion(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $secondQuery = (new Query($db))->select('id')->from('TotalTotalExample t2')->where('w > 5');
        $thirdQuery = (new Query($db))->select('id')->from('TotalTotalExample t3')->where('w = 3');
        $firtsQuery = (new Query($db))
            ->select('id')
            ->from('TotalExample t1')
            ->where(['and', 'w > 0', 'x < 2'])
            ->union($secondQuery)
            ->union($thirdQuery, true);

        [$sql, $queryParams] = $qb->build($firtsQuery);

        $this->assertSame(
            <<<SQL
            SELECT `id` FROM `TotalExample` `t1` WHERE (w > 0) AND (x < 2) UNION  SELECT `id` FROM `TotalTotalExample` `t2` WHERE w > 5 UNION ALL  SELECT `id` FROM `TotalTotalExample` `t3` WHERE w = 3
            SQL,
            $sql,
        );
        $this->assertSame([], $queryParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::buildWhereExists
     */
    public function testBuildWithWhereExists(string $cond, string $expectedQuerySql): void
    {
        parent::testBuildWithWhereExists($cond, $expectedQuerySql);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testCheckIntegrity(): void
    {
        $qb = $this->getConnection();

        $qb = $qb->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            PRAGMA foreign_keys=1
            SQL,
            $qb->checkIntegrity('', 'customer'),
        );
        $this->assertSame(
            <<<SQL
            PRAGMA foreign_keys=0
            SQL,
            $qb->checkIntegrity('', 'customer', false),
        );
    }

    public function testCreateIndexWithSchema(): void
    {
        $db = $this->getConnection();

        $command = $db->createCommand();
        $qb = $db->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            CREATE INDEX `myschema`.`myindex` ON `myindex` (`C_index_1`)
            SQL,
            $qb->createIndex('myschema' . '.' . 'myindex', 'myindex', 'C_index_1'),
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testCreateTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            CREATE TABLE `test` (
            \t`id` integer PRIMARY KEY AUTOINCREMENT NOT NULL,
            \t`name` varchar(255) NOT NULL,
            \t`email` varchar(255) NOT NULL,
            \t`status` integer NOT NULL,
            \t`created_at` datetime NOT NULL
            )
            SQL,
            $qb->createTable(
                'test',
                [
                    'id' => 'pk',
                    'name' => 'string(255) NOT NULL',
                    'email' => 'string(255) NOT NULL',
                    'status' => 'integer NOT NULL',
                    'created_at' => 'datetime NOT NULL',
                ],
            ),
        );
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::delete
     */
    public function testDelete(string $table, array|string $condition, string $expectedSQL, array $expectedParams): void
    {
        parent::testDelete($table, $condition, $expectedSQL, $expectedParams);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropCheck(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCheck is not supported by SQLite.');

        $qb->dropCheck('T_constraints_1', 'CN_check');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropCommentFromColumn(): void
    {
        $db = $this->getConnection(true);

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromColumn is not supported by SQLite.'
        );

        $qb->dropCommentFromColumn('customer', 'id');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropColumn is not supported by SQLite.'
        );

        $qb->dropColumn('customer', 'id');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropCommentFromTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropCommentFromTable is not supported by SQLite.'
        );

        $qb->dropCommentFromTable('customer');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropDefaultValue(): void
    {
        $db = $this->getConnection(true);

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::dropDefaultValue is not supported by SQLite.'
        );

        $qb->dropDefaultValue('T_constraints_1', 'CN_pk');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropForeignKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropForeignKey is not supported by SQLite.');

        $qb->dropForeignKey('T_constraints_3', 'CN_constraints_3');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropIndex(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            DROP INDEX `CN_constraints_2_single`
            SQL,
            $qb->dropIndex('T_constraints_2', 'CN_constraints_2_single'),
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropPrimaryKey(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropPrimaryKey is not supported by SQLite.');

        $qb->dropPrimaryKey('T_constraints_1', 'CN_pk');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testDropUnique(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\Db\Sqlite\DDLQueryBuilder::dropUnique is not supported by SQLite.');

        $qb->dropUnique('test_uq', 'test_uq_constraint');
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::insert
     */
    public function testInsert(
        string $table,
        array|QueryInterface $columns,
        array $params,
        string $expectedSQL,
        array $expectedParams
    ): void {
        parent::testInsert($table, $columns, $params, $expectedSQL, $expectedParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::insertWithReturningPks
     *
     * @throws Exception
     */
    public function testInsertWithReturningPks(
        string $table,
        array|QueryInterface $columns,
        array $params,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DMLQueryBuilder::insertWithReturningPks() is not supported by SQLite.'
        );

        $db = $this->getConnection(true);
        $qb = $db->getQueryBuilder();
        $qb->insertWithReturningPks($table, $columns, $params);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testRenameColumn(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\DDLQueryBuilder::renameColumn is not supported by SQLite.'
        );

        $qb->renameColumn('alpha', 'string_identifier', 'string_identifier_test');
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testRenameTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            ALTER TABLE `alpha` RENAME TO `alpha-test`
            SQL,
            $qb->renameTable('alpha', 'alpha-test'),
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testResetSequence(): void
    {
        $db = $this->getConnection(true);

        $qb = $db->getQueryBuilder();

        $this->assertSame(
            <<<SQL
            UPDATE sqlite_sequence SET seq=(SELECT MAX(`id`) FROM `item`) WHERE name='item'
            SQL,
            $qb->resetSequence('item'),
        );

        $this->assertSame(
            <<<SQL
            UPDATE sqlite_sequence SET seq='2' WHERE name='item'
            SQL,
            $qb->resetSequence('item', 3),
        );
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testTruncateTable(): void
    {
        $db = $this->getConnection();

        $qb = $db->getQueryBuilder();
        $sql = $qb->truncateTable('customer');

        $this->assertSame(
            <<<SQL
            DELETE FROM `customer`
            SQL,
            $sql,
        );

        $sql = $qb->truncateTable('T_constraints_1');

        $this->assertSame(
            <<<SQL
            DELETE FROM `T_constraints_1`
            SQL,
            $sql,
        );
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::update
     */
    public function testUpdate(
        string $table,
        array $columns,
        array|string $condition,
        string $expectedSQL,
        array $expectedParams
    ): void {
        parent::testUpdate($table, $columns, $condition, $expectedSQL, $expectedParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::upsert
     *
     * @throws Exception
     * @throws JsonException
     * @throws InvalidConfigException
     */
    public function testUpsert(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns,
        string $expectedSQL,
        array $expectedParams
    ): void {
        $db = $this->getConnection(true);

        $actualParams = [];
        $actualSQL = $db->getQueryBuilder()->upsert($table, $insertColumns, $updateColumns, $actualParams);

        $this->assertSame($expectedSQL, $actualSQL);

        $this->assertSame($expectedParams, $actualParams);
    }

    /**
     * @dataProvider \Yiisoft\Db\Sqlite\Tests\Provider\QueryBuilderProvider::upsert
     */
    public function testUpsertExecute(
        string $table,
        array|QueryInterface $insertColumns,
        array|bool $updateColumns
    ): void {
        parent::testUpsertExecute($table, $insertColumns, $updateColumns);
    }

    public function testJsonColumn()
    {
        $qb = $this->getConnection()->getQueryBuilder();
        $columnSchemaBuilder = new Column(SchemaInterface::TYPE_JSON);

        $this->assertSame(
            'ALTER TABLE `json_table` ADD `json_col` json',
            $qb->addColumn('json_table', 'json_col', $columnSchemaBuilder->asString()),
        );

        $this->assertSame(
            "CREATE TABLE `json_table` (\n\t`json_col` json\n)",
            $qb->createTable('json_table', ['json_col' => $columnSchemaBuilder]),
        );

        $this->assertSame(
            'INSERT INTO `json_table` (`json_col`) VALUES (:qp0)',
            $qb->insert('json_table', ['json_col' => ['a' => 1, 'b' => 2]]),
        );

        $this->assertSame(
            'INSERT INTO `json_table` (`json_col`) VALUES (:qp0)',
            $qb->insert('json_table', ['json_col' => new JsonExpression(['a' => 1, 'b' => 2])]),
        );
    }
}
