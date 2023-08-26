<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Expression\JsonExpression;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;

final class CommandProvider extends \Yiisoft\Db\Tests\Provider\CommandProvider
{
    use TestTrait;

    protected static string $driverName = 'sqlite';

    public static function batchInsert(): array
    {
        $batchInsert = parent::batchInsert();

        $batchInsert['batchInsert binds json params'] = [
            '{{%type}}',
            ['int_col', 'char_col', 'float_col', 'bool_col', 'json_col'],
            [
                [1, 'a', 0.0, true, ['a' => 1, 'b' => true, 'c' => [1, 2, 3]]],
                [2, 'b', -1.0, false, new JsonExpression(['d' => 'e', 'f' => false, 'g' => [4, 5, null]])],
            ],
            'expected' => 'INSERT INTO `type` (`int_col`, `char_col`, `float_col`, `bool_col`, `json_col`) '
                . 'VALUES (:qp0, :qp1, :qp2, :qp3, :qp4), (:qp5, :qp6, :qp7, :qp8, :qp9)',
            'expectedParams' => [
                ':qp0' => 1,
                ':qp1' => 'a',
                ':qp2' => 0.0,
                ':qp3' => true,
                ':qp4' => '{"a":1,"b":true,"c":[1,2,3]}',

                ':qp5' => 2,
                ':qp6' => 'b',
                ':qp7' => -1.0,
                ':qp8' => false,
                ':qp9' => '{"d":"e","f":false,"g":[4,5,null]}',
            ],
            2,
        ];

        return $batchInsert;
    }
}
