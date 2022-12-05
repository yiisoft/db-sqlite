<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Provider\BaseQueryBuilderProvider;
use Yiisoft\Db\Tests\Support\TraversableObject;

use function array_replace;

final class QueryBuilderProvider
{
    use TestTrait;

    public function batchInsert(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->batchInsert($this->getDriverName());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function buildCondition(): array
    {
        $db = $this->getConnection();

        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();
        $buildCondition = $baseQueryBuilderProvider->buildCondition($db);

        unset($buildCondition['inCondition-custom-1'], $buildCondition['inCondition-custom-2']);

        return array_merge($buildCondition, [
            'composite in using array objects' => [
                [
                    'in',
                    new TraversableObject(['id', 'name']),
                    new TraversableObject([['id' => 1, 'name' => 'oy'], ['id' => 2, 'name' => 'yo']]),
                ],
                '(([[id]] = :qp0 AND [[name]] = :qp1) OR ([[id]] = :qp2 AND [[name]] = :qp3))',
                [':qp0' => 1, ':qp1' => 'oy', ':qp2' => 2, ':qp3' => 'yo'],
            ],
            'composite in' => [
                ['in', ['id', 'name'], [['id' => 1, 'name' => 'oy']]],
                '(([[id]] = :qp0 AND [[name]] = :qp1))',
                [':qp0' => 1, ':qp1' => 'oy'],
            ],
            'composite in array values no exist' => [
                ['in', ['id', 'name', 'email'], [['id' => 1, 'name' => 'oy']]],
                '(([[id]] = :qp0 AND [[name]] = :qp1 AND [[email]] IS NULL))',
                [':qp0' => 1, ':qp1' => 'oy'],
            ],
            [
                ['in', ['id', 'name'], [['id' => 1, 'name' => 'foo'], ['id' => 2, 'name' => 'bar']]],
                '(([[id]] = :qp0 AND [[name]] = :qp1) OR ([[id]] = :qp2 AND [[name]] = :qp3))',
                [':qp0' => 1, ':qp1' => 'foo', ':qp2' => 2, ':qp3' => 'bar'],
            ],
            [
                ['not in', ['id', 'name'], [['id' => 1, 'name' => 'foo'], ['id' => 2, 'name' => 'bar']]],
                '(([[id]] != :qp0 OR [[name]] != :qp1) AND ([[id]] != :qp2 OR [[name]] != :qp3))',
                [':qp0' => 1, ':qp1' => 'foo', ':qp2' => 2, ':qp3' => 'bar'],
            ],
            'inCondition-custom-3' => [
                new InCondition(['id', 'name'], 'in', [['id' => 1]]),
                '(([[id]] = :qp0 AND [[name]] IS NULL))',
                [':qp0' => 1],
            ],
            'inCondition-custom-4' => [
                new InCondition(['id', 'name'], 'in', [['name' => 'oy']]),
                '(([[id]] IS NULL AND [[name]] = :qp0))',
                [':qp0' => 'oy'],
            ],
            'inCondition-custom-5' => [
                new InCondition(['id', 'name'], 'in', [['id' => 1, 'name' => 'oy']]),
                '(([[id]] = :qp0 AND [[name]] = :qp1))',
                [':qp0' => 1, ':qp1' => 'oy'],
            ],
            'like-custom-1' => [['like', 'a', 'b'], '[[a]] LIKE :qp0 ESCAPE \'\\\'', [':qp0' => '%b%']],
            'like-custom-2' => [
                ['like', 'a', new Expression(':qp0', [':qp0' => '%b%'])],
                '[[a]] LIKE :qp0 ESCAPE \'\\\'',
                [':qp0' => '%b%'],
            ],
            'like-custom-3' => [
                ['like', new Expression('CONCAT(col1, col2)'), 'b'],
                'CONCAT(col1, col2) LIKE :qp0 ESCAPE \'\\\'',
                [':qp0' => '%b%'],
            ],
        ]);
    }

    public function buildFrom(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->buildFrom($this->getDriverName());
    }

    public function buildLikeCondition(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->buildLikeCondition($this->getDriverName(), " ESCAPE '\\'");
    }

    public function buildWhereExists(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->buildWhereExists($this->getDriverName());
    }

    public function delete(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->delete($this->getDriverName());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function insert(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->insert($this->getConnection());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function insertEx(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->insertEx($this->getConnection());
    }

    public function update(): array
    {
        $baseQueryBuilderProvider = new BaseQueryBuilderProvider();

        return $baseQueryBuilderProvider->update($this->getDriverName());
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function upsert(): array
    {
        $concreteData = [
            'regular values' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `address`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=(SELECT `address` FROM `EXCLUDED`), `status`=(SELECT `status` FROM `EXCLUDED`), `profile_id`=(SELECT `profile_id` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);
                SQL,
            ],
            'regular values with update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `address`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=:qp4, `status`=:qp5, `orders`=T_upsert.orders + 1 WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);
                SQL,
            ],
            'regular values without update part' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3)
                SQL,
            ],
            'query' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `status`) AS (SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1) UPDATE `T_upsert` SET `status`=(SELECT `status` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1;
                SQL,
            ],
            'query with update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `status`) AS (SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1) UPDATE `T_upsert` SET `address`=:qp1, `status`=:qp2, `orders`=T_upsert.orders + 1 WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1;
                SQL,
            ],
            'query without update part' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO `T_upsert` (`email`, `status`) SELECT `email`, 2 AS `status` FROM `customer` WHERE `name`=:qp0 LIMIT 1
                SQL,
            ],
            'values and expressions' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())
                SQL,
            ],
            'values and expressions with update part' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())
                SQL,
            ],
            'values and expressions without update part' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) VALUES (:qp0, now())
                SQL,
            ],
            'query, values and expressions with update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, [[time]]) AS (SELECT :phEmail AS `email`, now() AS [[time]]) UPDATE {{%T_upsert}} SET `ts`=:qp1, [[orders]]=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[time]]) SELECT :phEmail AS `email`, now() AS [[time]];
                SQL,
            ],
            'query, values and expressions without update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, [[time]]) AS (SELECT :phEmail AS `email`, now() AS [[time]]) UPDATE {{%T_upsert}} SET `ts`=:qp1, [[orders]]=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[time]]) SELECT :phEmail AS `email`, now() AS [[time]];
                SQL,
            ],
            'no columns to update' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO `T_upsert_1` (`a`) VALUES (:qp0)
                SQL,
            ],
        ];

        $newData = (new BaseQueryBuilderProvider())->upsert($this->getConnection());

        foreach ($concreteData as $testName => $data) {
            $newData[$testName] = array_replace($newData[$testName], $data);
        }

        return $newData;
    }
}
