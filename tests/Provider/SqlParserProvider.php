<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

class SqlParserProvider extends \Yiisoft\Db\Tests\Provider\SqlParserProvider
{
    public static function getNextPlaceholder(): array
    {
        return [
            ...parent::getNextPlaceholder(),
            [
                '`:field` = :name AND age = :age',
                ':name',
                11,
            ],
            [
                '[:field] = :name AND age = :age',
                ':name',
                11,
            ],
            [
                '[[:field]] = :name AND age = :age',
                ':name',
                13,
            ],
        ];
    }
}
