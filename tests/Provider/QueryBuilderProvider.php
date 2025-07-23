<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Command\Param;
use Yiisoft\Db\Constant\DataType;
use Yiisoft\Db\Constant\PseudoType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Support\TraversableObject;

use function array_replace;
use function array_splice;

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

        return [
            ...$buildCondition,
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
                new In(['id', 'name'], 'in', [['id' => 1]]),
                '(([[id]] = :qp0 AND [[name]] IS NULL))',
                [':qp0' => 1],
            ],
            'inCondition-custom-4' => [
                new In(['id', 'name'], 'in', [['name' => 'oy']]),
                '(([[id]] IS NULL AND [[name]] = :qp0))',
                [':qp0' => 'oy'],
            ],
            'inCondition-custom-5' => [
                new In(['id', 'name'], 'in', [['id' => 1, 'name' => 'oy']]),
                '(([[id]] = :qp0 AND [[name]] = :qp1))',
                [':qp0' => 1, ':qp1' => 'oy'],
            ],
            'like-custom-1' => [['like', 'a', 'b'], '[[a]] LIKE :qp0 ESCAPE \'\\\'', [':qp0' => new Param('%b%', DataType::STRING)]],
            'like-custom-2' => [
                ['like', 'a', new Expression(':qp0', [':qp0' => '%b%'])],
                '[[a]] LIKE :qp0 ESCAPE \'\\\'',
                [':qp0' => '%b%'],
            ],
            'like-custom-3' => [
                ['like', new Expression('CONCAT(col1, col2)'), 'b'],
                'CONCAT(col1, col2) LIKE :qp0 ESCAPE \'\\\'',
                [':qp0' => new Param('%b%', DataType::STRING)],
            ],
        ];
    }

    public static function insert(): array
    {
        $insert = parent::insert();

        $insert['empty columns'][3] = <<<SQL
        INSERT INTO "customer" DEFAULT VALUES
        SQL;

        return $insert;
    }

    public static function insertReturningPks(): array
    {
        return [
            'regular-values' => [
                'customer',
                [
                    'email' => 'test@example.com',
                    'name' => 'John Doe',
                    'address' => 'New York {{city}}',
                    'is_active' => false,
                    'related_id' => null,
                ],
                [],
                <<<SQL
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id") VALUES (:qp0, :qp1, :qp2, :qp3, :qp4) RETURNING "id"
                SQL,
                [
                    ':qp0' => 'test@example.com',
                    ':qp1' => 'John Doe',
                    ':qp2' => 'New York {{city}}',
                    ':qp3' => false,
                    ':qp4' => null,
                ],
            ],
            'params-and-expressions' => [
                '{{%type}}',
                ['{{%type}}.[[related_id]]' => null, '[[time]]' => new Expression('now()')],
                [],
                <<<SQL
                INSERT INTO {{%type}} ("related_id", "time") VALUES (:qp0, now())
                SQL,
                [':qp0' => null],
            ],
            'carry passed params' => [
                'customer',
                [
                    'email' => 'test@example.com',
                    'name' => 'John Doe',
                    'address' => '{{city}}',
                    'is_active' => false,
                    'related_id' => null,
                    'col' => new Expression('CONCAT(:phFoo, :phBar)', [':phFoo' => 'foo']),
                ],
                [':phBar' => 'bar'],
                <<<SQL
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id", "col") VALUES (:qp1, :qp2, :qp3, :qp4, :qp5, CONCAT(:phFoo, :phBar)) RETURNING "id"
                SQL,
                [
                    ':phBar' => 'bar',
                    ':qp1' => 'test@example.com',
                    ':qp2' => 'John Doe',
                    ':qp3' => '{{city}}',
                    ':qp4' => false,
                    ':qp5' => null,
                    ':phFoo' => 'foo',
                ],
            ],
            'carry passed params (query)' => [
                'customer',
                (new Query(self::getDb()))
                    ->select(['email', 'name', 'address', 'is_active', 'related_id'])
                    ->from('customer')
                    ->where(
                        [
                            'email' => 'test@example.com',
                            'name' => 'John Doe',
                            'address' => '{{city}}',
                            'is_active' => false,
                            'related_id' => null,
                            'col' => new Expression('CONCAT(:phFoo, :phBar)', [':phFoo' => 'foo']),
                        ],
                    ),
                [':phBar' => 'bar'],
                <<<SQL
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id") SELECT "email", "name", "address", "is_active", "related_id" FROM "customer" WHERE ("email"=:qp1) AND ("name"=:qp2) AND ("address"=:qp3) AND ("is_active"=FALSE) AND ("related_id" IS NULL) AND ("col"=CONCAT(:phFoo, :phBar)) RETURNING "id"
                SQL,
                [
                    ':phBar' => 'bar',
                    ':qp1' => new Param('test@example.com', DataType::STRING),
                    ':qp2' => new Param('John Doe', DataType::STRING),
                    ':qp3' => new Param('{{city}}', DataType::STRING),
                    ':phFoo' => 'foo',
                ],
            ],
            [
                '{{%order_item}}',
                ['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 1.0],
                [],
                <<<SQL
                INSERT INTO {{%order_item}} ("order_id", "item_id", "quantity", "subtotal") VALUES (:qp0, :qp1, :qp2, :qp3) RETURNING "order_id", "item_id"
                SQL,
                [':qp0' => 1, ':qp1' => 1, ':qp2' => 1, ':qp3' => 1.0,],
            ],
        ];
    }

    public static function upsert(): array
    {
        $concreteData = [
            'regular values' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, :qp2, :qp3) ON CONFLICT ("email") DO UPDATE SET "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"
                SQL,
            ],
            'regular values with unique at not the first position' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("address", "email", "status", "profile_id") VALUES (:qp0, :qp1, :qp2, :qp3) ON CONFLICT ("email") DO UPDATE SET "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"
                SQL,
            ],
            'regular values with update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, :qp2, :qp3) ON CONFLICT ("email") DO UPDATE SET "address"=:qp4, "status"=:qp5, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'regular values without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, :qp2, :qp3) ON CONFLICT DO NOTHING
                SQL,
            ],
            'query' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name"=:qp0 LIMIT 1 ON CONFLICT ("email") DO UPDATE SET "status"=EXCLUDED."status"
                SQL,
            ],
            'query with update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name"=:qp0 LIMIT 1 ON CONFLICT ("email") DO UPDATE SET "address"=:qp1, "status"=:qp2, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'query without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name"=:qp0 LIMIT 1 ON CONFLICT DO NOTHING
                SQL,
            ],
            'values and expressions' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ("email", "ts") VALUES (:qp0, CURRENT_TIMESTAMP) ON CONFLICT ("email") DO UPDATE SET "ts"=EXCLUDED."ts"
                SQL,
            ],
            'values and expressions with update part' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ("email", "ts") VALUES (:qp0, CURRENT_TIMESTAMP) ON CONFLICT ("email") DO UPDATE SET "orders"=T_upsert.orders + 1
                SQL,
            ],
            'values and expressions without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "ts") VALUES (:qp0, CURRENT_TIMESTAMP) ON CONFLICT DO NOTHING
                SQL,
            ],
            'query, values and expressions with update part' => [
                3 => <<<SQL
                INSERT INTO {{%T_upsert}} ("email", [[ts]]) SELECT :phEmail AS "email", CURRENT_TIMESTAMP AS [[ts]] ON CONFLICT ("email") DO UPDATE SET "ts"=:qp1, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'query, values and expressions without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", [[ts]]) SELECT :phEmail AS "email", CURRENT_TIMESTAMP AS [[ts]] ON CONFLICT DO NOTHING
                SQL,
            ],
            'no columns to update' => [
                3 => <<<SQL
                INSERT INTO "T_upsert_1" ("a") VALUES (:qp0) ON CONFLICT DO NOTHING
                SQL,
            ],
            'no columns to update with unique' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email") VALUES (:qp0) ON CONFLICT DO NOTHING
                SQL,
            ],
            'no unique columns in table - simple insert' => [
                3 => <<<SQL
                INSERT INTO {{%animal}} ("type") VALUES (:qp0)
                SQL,
            ],
        ];

        $upsert = parent::upsert();

        foreach ($concreteData as $testName => $data) {
            $upsert[$testName] = array_replace($upsert[$testName], $data);
        }

        return $upsert;
    }

    public static function upsertReturning(): array
    {
        $upsert = self::upsert();

        $withoutUpdate = [
            'regular values without update part',
            'query without update part',
            'values and expressions without update part',
            'query, values and expressions without update part',
            'no columns to update with unique',
        ];

        foreach ($upsert as $name => &$data) {
            array_splice($data, 3, 0, [['id']]);
            if (in_array($name, $withoutUpdate, true)) {
                $data[4] = substr($data[4], 0, -10) . 'DO UPDATE SET "ts" = "ts"';
            }

            $data[4] .= ' RETURNING "id"';
        }

        $upsert['no columns to update'][3] = ['a'];
        $upsert['no columns to update'][4] = 'INSERT INTO "T_upsert_1" ("a") VALUES (:qp0) ON CONFLICT DO UPDATE SET "a" = "a" RETURNING "a"';

        return [
            ...$upsert,
            'composite primary key' => [
                'notauto_pk',
                ['id_1' => 1, 'id_2' => 2.5, 'type' => 'Test'],
                true,
                ['id_1', 'id_2'],
                'INSERT INTO "notauto_pk" ("id_1", "id_2", "type") VALUES (:qp0, :qp1, :qp2)'
                . ' ON CONFLICT ("id_1", "id_2") DO UPDATE SET "type"=EXCLUDED."type" RETURNING "id_1", "id_2"',
                [':qp0' => 1, ':qp1' => 2.5, ':qp2' => 'Test'],
            ],
            'no return columns' => [
                'type',
                ['int_col' => 3, 'char_col' => 'a', 'float_col' => 1.2, 'bool_col' => true],
                true,
                [],
                'INSERT INTO "type" ("int_col", "char_col", "float_col", "bool_col") VALUES (:qp0, :qp1, :qp2, :qp3)',
                [':qp0' => 3, ':qp1' => 'a', ':qp2' => 1.2, ':qp3' => true],
            ],
            'return all columns' => [
                'T_upsert',
                ['email' => 'test@example.com', 'address' => 'test address', 'status' => 1, 'profile_id' => 1],
                true,
                null,
                'INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, :qp2, :qp3)'
                . ' ON CONFLICT ("email") DO UPDATE SET'
                . ' "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"'
                . ' RETURNING "id", "ts", "email", "recovery_email", "address", "status", "orders", "profile_id"',
                [':qp0' => 'test@example.com', ':qp1' => 'test address', ':qp2' => 1, ':qp3' => 1],
            ],
        ];
    }

    public static function buildColumnDefinition(): array
    {
        $values = parent::buildColumnDefinition();

        $values[PseudoType::PK][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values[PseudoType::UPK][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values[PseudoType::BIGPK][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values[PseudoType::UBIGPK][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values[PseudoType::UUID_PK][0] = 'blob(16) PRIMARY KEY NOT NULL DEFAULT (randomblob(16))';
        $values[PseudoType::UUID_PK_SEQ][0] = 'blob(16) PRIMARY KEY NOT NULL DEFAULT (randomblob(16))';
        $values['primaryKey()'][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values['primaryKey(false)'][0] = 'integer PRIMARY KEY NOT NULL';
        $values['smallPrimaryKey()'][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values['smallPrimaryKey(false)'][0] = 'smallint PRIMARY KEY NOT NULL';
        $values['bigPrimaryKey()'][0] = 'integer PRIMARY KEY AUTOINCREMENT NOT NULL';
        $values['bigPrimaryKey(false)'][0] = 'bigint PRIMARY KEY NOT NULL';
        $values['uuidPrimaryKey()'][0] = 'blob(16) PRIMARY KEY NOT NULL DEFAULT (randomblob(16))';
        $values['uuidPrimaryKey(false)'][0] = 'blob(16) PRIMARY KEY NOT NULL';
        $values['money()'][0] = 'decimal(19,4)';
        $values['money(10)'][0] = 'decimal(10,4)';
        $values['money(10,2)'][0] = 'decimal(10,2)';
        $values['money(null)'][0] = 'decimal';
        $values['binary()'][0] = 'blob';
        $values['binary(1000)'][0] = 'blob(1000)';
        $values['uuid()'][0] = 'blob(16)';
        $values["comment('comment')"][0] = 'varchar(255) /* comment */';
        $values['integer()->primaryKey()'][0] = 'integer PRIMARY KEY NOT NULL';
        $values['string()->primaryKey()'][0] = 'varchar(255) PRIMARY KEY NOT NULL';
        $values['unsigned()'][0] = 'integer';

        return $values;
    }

    public static function prepareParam(): array
    {
        $values = parent::prepareParam();

        $values['binary'][0] = "x'737472696e67'";
        $values['resource'][0] = "x'737472696e67'";

        return $values;
    }

    public static function prepareValue(): array
    {
        $values = parent::prepareValue();

        $values['binary'][0] = "x'737472696e67'";
        $values['paramBinary'][0] = "x'737472696e67'";
        $values['paramResource'][0] = "x'737472696e67'";

        return $values;
    }
}
