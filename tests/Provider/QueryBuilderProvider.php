<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Constant\DataType;
use Yiisoft\Db\Constant\PseudoType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\Function\ArrayMerge;
use Yiisoft\Db\Expression\Value\ArrayValue;
use Yiisoft\Db\Expression\Value\Param;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\Sqlite\Column\ColumnBuilder;
use Yiisoft\Db\Tests\Support\TraversableObject;

use function array_replace;
use function array_splice;
use function strtr;

final class QueryBuilderProvider extends \Yiisoft\Db\Tests\Provider\QueryBuilderProvider
{
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
                new In(['id', 'name'], [['id' => 1]]),
                '(([[id]] = :qp0 AND [[name]] IS NULL))',
                [':qp0' => 1],
            ],
            'inCondition-custom-4' => [
                new In(['id', 'name'], [['name' => 'oy']]),
                '(([[id]] IS NULL AND [[name]] = :qp0))',
                [':qp0' => 'oy'],
            ],
            'inCondition-custom-5' => [
                new In(['id', 'name'], [['id' => 1, 'name' => 'oy']]),
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
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id") VALUES (:qp0, :qp1, :qp2, FALSE, NULL) RETURNING "id"
                SQL,
                [
                    ':qp0' => new Param('test@example.com', DataType::STRING),
                    ':qp1' => new Param('John Doe', DataType::STRING),
                    ':qp2' => new Param('New York {{city}}', DataType::STRING),
                ],
            ],
            'params-and-expressions' => [
                '{{%type}}',
                ['{{%type}}.[[related_id]]' => null, '[[time]]' => new Expression('now()')],
                [],
                <<<SQL
                INSERT INTO {{%type}} ("related_id", "time") VALUES (NULL, now())
                SQL,
                [],
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
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id", "col") VALUES (:qp1, :qp2, :qp3, FALSE, NULL, CONCAT(:phFoo, :phBar)) RETURNING "id"
                SQL,
                [
                    ':phBar' => 'bar',
                    ':qp1' => new Param('test@example.com', DataType::STRING),
                    ':qp2' => new Param('John Doe', DataType::STRING),
                    ':qp3' => new Param('{{city}}', DataType::STRING),
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
                INSERT INTO "customer" ("email", "name", "address", "is_active", "related_id") SELECT "email", "name", "address", "is_active", "related_id" FROM "customer" WHERE ("email" = :qp1) AND ("name" = :qp2) AND ("address" = :qp3) AND ("is_active" = FALSE) AND ("related_id" IS NULL) AND ("col" = CONCAT(:phFoo, :phBar)) RETURNING "id"
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
                INSERT INTO {{%order_item}} ("order_id", "item_id", "quantity", "subtotal") VALUES (1, 1, 1, 1) RETURNING "order_id", "item_id"
                SQL,
                [],
            ],
        ];
    }

    public static function upsert(): array
    {
        $concreteData = [
            'regular values' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, 1, NULL) ON CONFLICT ("email") DO UPDATE SET "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"
                SQL,
            ],
            'regular values with unique at not the first position' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("address", "email", "status", "profile_id") VALUES (:qp0, :qp1, 1, NULL) ON CONFLICT ("email") DO UPDATE SET "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"
                SQL,
            ],
            'regular values with update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, 1, NULL) ON CONFLICT ("email") DO UPDATE SET "address"=:qp2, "status"=2, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'regular values without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, 1, NULL) ON CONFLICT DO NOTHING
                SQL,
            ],
            'query' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name" = :qp0 LIMIT 1 ON CONFLICT ("email") DO UPDATE SET "status"=EXCLUDED."status"
                SQL,
            ],
            'query with update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name" = :qp0 LIMIT 1 ON CONFLICT ("email") DO UPDATE SET "address"=:qp1, "status"=2, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'query without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "status") SELECT "email", 2 AS "status" FROM "customer" WHERE "name" = :qp0 LIMIT 1 ON CONFLICT DO NOTHING
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
                INSERT INTO {{%T_upsert}} ("email", "ts") SELECT :phEmail AS "email", CURRENT_TIMESTAMP AS [[ts]] ON CONFLICT ("email") DO UPDATE SET "ts"=0, "orders"=T_upsert.orders + 1
                SQL,
            ],
            'query, values and expressions without update part' => [
                3 => <<<SQL
                INSERT INTO "T_upsert" ("email", "ts") SELECT :phEmail AS "email", CURRENT_TIMESTAMP AS [[ts]] ON CONFLICT DO NOTHING
                SQL,
            ],
            'no columns to update' => [
                3 => <<<SQL
                INSERT INTO "T_upsert_1" ("a") VALUES (1) ON CONFLICT DO NOTHING
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
        $upsert['no columns to update'][4] = 'INSERT INTO "T_upsert_1" ("a") VALUES (1) ON CONFLICT DO UPDATE SET "a" = "a" RETURNING "a"';

        return [
            ...$upsert,
            'composite primary key' => [
                'notauto_pk',
                ['id_1' => 1, 'id_2' => 2.5, 'type' => 'Test'],
                true,
                ['id_1', 'id_2'],
                'INSERT INTO "notauto_pk" ("id_1", "id_2", "type") VALUES (1, 2.5, :qp0)'
                . ' ON CONFLICT ("id_1", "id_2") DO UPDATE SET "type"=EXCLUDED."type" RETURNING "id_1", "id_2"',
                [':qp0' => new Param('Test', DataType::STRING)],
            ],
            'no return columns' => [
                'type',
                ['int_col' => 3, 'char_col' => 'a', 'float_col' => 1.2, 'bool_col' => true],
                true,
                [],
                'INSERT INTO "type" ("int_col", "char_col", "float_col", "bool_col") VALUES (3, :qp0, 1.2, TRUE)',
                [':qp0' => new Param('a', DataType::STRING)],
            ],
            'return all columns' => [
                'T_upsert',
                ['email' => 'test@example.com', 'address' => 'test address', 'status' => 1, 'profile_id' => 1],
                true,
                null,
                'INSERT INTO "T_upsert" ("email", "address", "status", "profile_id") VALUES (:qp0, :qp1, 1, 1)'
                . ' ON CONFLICT ("email") DO UPDATE SET'
                . ' "address"=EXCLUDED."address", "status"=EXCLUDED."status", "profile_id"=EXCLUDED."profile_id"'
                . ' RETURNING "id", "ts", "email", "recovery_email", "address", "status", "orders", "profile_id"',
                [
                    ':qp0' => new Param('test@example.com', DataType::STRING),
                    ':qp1' => new Param('test address', DataType::STRING),
                ],
            ],
        ];
    }

    public static function buildColumnDefinition(): array
    {
        $values = parent::buildColumnDefinition();

        // SQLite does not support unsigned types
        unset(
            $values['bigint(15) unsigned'],
            $values['unsigned()'],
        );

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
        $values["collation('collation_name')"] = [
            'varchar(255) COLLATE RTRIM',
            ColumnBuilder::string()->collation('RTRIM'),
        ];
        $values["comment('comment')"][0] = 'varchar(255) /* comment */';
        $values['integer()->primaryKey()'][0] = 'integer PRIMARY KEY NOT NULL';
        $values['string()->primaryKey()'][0] = 'varchar(255) PRIMARY KEY NOT NULL';

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
        $values['ResourceStream'][0] = "x'737472696e67'";

        return $values;
    }

    public static function multiOperandFunctionClasses(): array
    {
        return [
            ...parent::multiOperandFunctionClasses(),
            ArrayMerge::class => [ArrayMerge::class],
        ];
    }

    public static function multiOperandFunctionBuilder(): array
    {
        $data = parent::multiOperandFunctionBuilder();

        $intQuery = self::getDb()->select(10);
        $intQuerySql = '(SELECT 10)';
        $stringParam = new Param('[3,4,5]', DataType::STRING);

        foreach ($data as &$value) {
            $value[2] = strtr($value[2], [
                'GREATEST(' => 'MAX(',
                'LEAST(' => 'MIN(',
            ]);
        }

        return [
            ...$data,
            'ArrayMerge with 1 operand' => [
                ArrayMerge::class,
                [[1, 2, 3]],
                '(:qp0)',
                [1, 2, 3],
                [':qp0' => new Param('[1,2,3]', DataType::STRING)],
            ],
            'ArrayMerge with 2 operands' => [
                ArrayMerge::class,
                [[1, 2, 3], $stringParam],
                '(SELECT json_group_array(value) AS value FROM (SELECT value FROM json_each(:qp0) UNION SELECT value FROM json_each(:qp1)))',
                [1, 2, 3, 4, 5],
                [
                    ':qp0' => new Param('[1,2,3]', DataType::STRING),
                    ':qp1' => $stringParam,
                ],
            ],
            'ArrayMerge with 4 operands' => [
                ArrayMerge::class,
                [[1, 2, 3], new ArrayValue([5, 6, 7]), $stringParam, $intQuery],
                "(SELECT json_group_array(value) AS value FROM (SELECT value FROM json_each(:qp0) UNION SELECT value FROM json_each(:qp1) UNION SELECT value FROM json_each(:qp2) UNION SELECT value FROM json_each($intQuerySql)))",
                [1, 2, 3, 4, 5, 6, 7, 10],
                [
                    ':qp0' => new Param('[1,2,3]', DataType::STRING),
                    ':qp1' => new Param('[5,6,7]', DataType::STRING),
                    ':qp2' => $stringParam,
                ],
            ],
        ];
    }

    public static function upsertWithMultiOperandFunctions(): array
    {
        $data = parent::upsertWithMultiOperandFunctions();

        $data[0][3] = 'INSERT INTO "test_upsert_with_functions"'
            . ' ("id", "array_col", "greatest_col", "least_col", "longest_col", "shortest_col")'
            . ' VALUES (1, :qp0, 5, 5, :qp1, :qp2) ON CONFLICT ("id") DO UPDATE SET'
            . ' "array_col"=(SELECT json_group_array(value) AS value FROM (SELECT value FROM json_each("test_upsert_with_functions"."array_col") UNION SELECT value FROM json_each(EXCLUDED."array_col") ORDER BY value)),'
            . ' "greatest_col"=MAX("test_upsert_with_functions"."greatest_col", EXCLUDED."greatest_col"),'
            . ' "least_col"=MIN("test_upsert_with_functions"."least_col", EXCLUDED."least_col"),'
            . ' "longest_col"=(SELECT value FROM (SELECT "test_upsert_with_functions"."longest_col" AS value UNION SELECT EXCLUDED."longest_col" AS value) AS t ORDER BY LENGTH(value) DESC LIMIT 1),'
            . ' "shortest_col"=(SELECT value FROM (SELECT "test_upsert_with_functions"."shortest_col" AS value UNION SELECT EXCLUDED."shortest_col" AS value) AS t ORDER BY LENGTH(value) ASC LIMIT 1)';

        return $data;
    }
}
