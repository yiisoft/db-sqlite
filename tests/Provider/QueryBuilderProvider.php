<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Support\TraversableObject;

use function array_replace;

final class QueryBuilderProvider extends \Yiisoft\Db\Tests\Provider\QueryBuilderProvider
{
    use TestTrait;

    protected static string $driverName = 'sqlite';
    protected static string $likeEscapeCharSql = " ESCAPE '\\'";

    public static function buildCondition(): array
    {
        $buildCondition = parent::buildCondition();

        unset(
            $buildCondition['inCondition-custom-1'],
            $buildCondition['inCondition-custom-2'],
            $buildCondition['inCondition-custom-6'],
        );

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
            'composite in with Expression' => [
                ['in',
                    [new Expression('id'), new Expression('name')],
                    [['id' => 1, 'name' => 'oy']],
                ],
                '((id = :qp0 AND name = :qp1))',
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

            /* json conditions */
            [
                ['=', 'json_col', new JsonExpression(['type' => 'iron', 'weight' => 15])],
                '[[json_col]] = :qp0', [':qp0' => '{"type":"iron","weight":15}'],
            ],
            'object with type, that is ignored in SQLite' => [
                ['=', 'json_col', new JsonExpression(['type' => 'iron', 'weight' => 15], 'json')],
                '[[json_col]] = :qp0', [':qp0' => '{"type":"iron","weight":15}'],
            ],
            'false value' => [
                ['=', 'json_col', new JsonExpression([false])],
                '[[json_col]] = :qp0', [':qp0' => '[false]'],
            ],
            'null value' => [
                ['=', 'json_col', new JsonExpression(null)],
                '[[json_col]] = :qp0', [':qp0' => 'null'],
            ],
            'null as array value' => [
                ['=', 'json_col', new JsonExpression([null])],
                '[[json_col]] = :qp0', [':qp0' => '[null]'],
            ],
            'null as object value' => [
                ['=', 'json_col', new JsonExpression(['nil' => null])],
                '[[json_col]] = :qp0', [':qp0' => '{"nil":null}'],
            ],
            'query' => [
                [
                    '=',
                    'json_col',
                    new JsonExpression((new Query(self::getDb()))->select('params')->from('user')->where(['id' => 1])),
                ],
                '[[json_col]] = (SELECT [[params]] FROM [[user]] WHERE [[id]]=:qp0)',
                [':qp0' => 1],
            ],
            'query with type, that is ignored in SQLite' => [
                [
                    '=',
                    'json_col',
                    new JsonExpression(
                        (new Query(self::getDb()))->select('params')->from('user')->where(['id' => 1]),
                        'json'
                    ),
                ],
                '[[json_col]] = (SELECT [[params]] FROM [[user]] WHERE [[id]]=:qp0)', [':qp0' => 1],
            ],
            'nested and combined json expression' => [
                [
                    '=',
                    'json_col',
                    new JsonExpression(
                        new JsonExpression(['a' => 1, 'b' => 2, 'd' => new JsonExpression(['e' => 3])])
                    ),
                ],
                '[[json_col]] = :qp0', [':qp0' => '{"a":1,"b":2,"d":{"e":3}}'],
            ],
            'search by property in JSON column' => [
                ['=', new Expression("(json_col->>'$.someKey')"), 42],
                "(json_col->>'$.someKey') = :qp0", [':qp0' => 42],
            ],
        ]);
    }

    public static function insert(): array
    {
        $insert = parent::insert();

        $insert['empty columns'][3] = <<<SQL
        INSERT INTO `customer` DEFAULT VALUES
        SQL;

        return $insert;
    }

    public static function upsert(): array
    {
        $concreteData = [
            'regular values' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `address`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=(SELECT `address` FROM `EXCLUDED`), `status`=(SELECT `status` FROM `EXCLUDED`), `profile_id`=(SELECT `profile_id` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`email`, `address`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);
                SQL,
            ],
            'regular values with unique at not the first position' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`address`, `email`, `status`, `profile_id`) AS (VALUES (:qp0, :qp1, :qp2, :qp3)) UPDATE `T_upsert` SET `address`=(SELECT `address` FROM `EXCLUDED`), `status`=(SELECT `status` FROM `EXCLUDED`), `profile_id`=(SELECT `profile_id` FROM `EXCLUDED`) WHERE `T_upsert`.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO `T_upsert` (`address`, `email`, `status`, `profile_id`) VALUES (:qp0, :qp1, :qp2, :qp3);
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
                WITH "EXCLUDED" (`email`, `ts`) AS (VALUES (:qp0, CURRENT_TIMESTAMP)) UPDATE {{%T_upsert}} SET `ts`=(SELECT `ts` FROM `EXCLUDED`) WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, `ts`) VALUES (:qp0, CURRENT_TIMESTAMP);
                SQL,
            ],
            'values and expressions with update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, `ts`) AS (VALUES (:qp0, CURRENT_TIMESTAMP)) UPDATE {{%T_upsert}} SET `orders`=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, `ts`) VALUES (:qp0, CURRENT_TIMESTAMP);
                SQL,
            ],
            'values and expressions without update part' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO {{%T_upsert}} (`email`, `ts`) VALUES (:qp0, CURRENT_TIMESTAMP)
                SQL,
            ],
            'query, values and expressions with update part' => [
                3 => <<<SQL
                WITH "EXCLUDED" (`email`, [[ts]]) AS (SELECT :phEmail AS `email`, CURRENT_TIMESTAMP AS [[ts]]) UPDATE {{%T_upsert}} SET `ts`=:qp1, `orders`=T_upsert.orders + 1 WHERE {{%T_upsert}}.`email`=(SELECT `email` FROM `EXCLUDED`); INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[ts]]) SELECT :phEmail AS `email`, CURRENT_TIMESTAMP AS [[ts]];
                SQL,
            ],
            'query, values and expressions without update part' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO {{%T_upsert}} (`email`, [[ts]]) SELECT :phEmail AS `email`, CURRENT_TIMESTAMP AS [[ts]]
                SQL,
            ],
            'no columns to update' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO `T_upsert_1` (`a`) VALUES (:qp0)
                SQL,
            ],
            'no columns to update with unique' => [
                3 => <<<SQL
                INSERT OR IGNORE INTO {{%T_upsert}} (`email`) VALUES (:qp0)
                SQL,
            ],
            'no unique columns in table - simple insert' => [
                3 => <<<SQL
                INSERT INTO {{%animal}} (`type`) VALUES (:qp0)
                SQL,
            ],
        ];

        $upsert = parent::upsert();

        foreach ($concreteData as $testName => $data) {
            $upsert[$testName] = array_replace($upsert[$testName], $data);
        }

        return $upsert;
    }
}
