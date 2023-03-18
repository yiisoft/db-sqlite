<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Schema\SchemaInterface;

final class ColumnSchemaBuilderProvider extends \Yiisoft\Db\Tests\Provider\ColumnSchemaBuilderProvider
{
    protected static string $driverName = 'sqlite';

    public static function types(): array
    {
        $types = parent::types();

        $types[0][0] = 'integer UNSIGNED NULL DEFAULT NULL';
        $types[1][0] = 'integer(10) UNSIGNED';

        return array_merge(
            $types,
            [
                ['integer UNSIGNED', SchemaInterface::TYPE_INTEGER, null, [['unsigned']]],
            ],
        );
    }

    public static function createColumnTypes(): array
    {
        $types = parent::createColumnTypes();

        $types['uuid'][0] = '`column` blob(16)';
        $types['uuid not null'][0] = '`column` blob(16) NOT NULL';

        $types['uuid with default'][0] = '`column` blob(16) DEFAULT (UNHEX(REPLACE(\'875343b3-6bd0-4bec-81bb-aa68bb52d945\',\'-\',\'\')))';
        $types['uuid with default'][3] = [['defaultExpression', '(UNHEX(REPLACE(\'875343b3-6bd0-4bec-81bb-aa68bb52d945\',\'-\',\'\')))']];

        $types['uuid pk'][0] = '`column` blob(16) PRIMARY KEY';
        $types['uuid pk not null'][0] = '`column` blob(16) PRIMARY KEY NOT NULL';

        $types['uuid pk not null with default'][0] = '`column` blob(16) PRIMARY KEY NOT NULL DEFAULT (RANDOMBLOB(16))';
        $types['uuid pk not null with default'][3] = [['notNull'],['defaultExpression', '(RANDOMBLOB(16))']];

        return $types;
    }
}
