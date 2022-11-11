<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Tests\Provider\BaseSchemaProvider;
use Yiisoft\Db\Tests\Support\AnyValue;

final class SchemaProvider
{
    public function constraints(): array
    {
        $baseSchemaProvider = new BaseSchemaProvider();
        $constraints = $baseSchemaProvider->constraints();

        $constraints['1: primary key'][2]->name(null);
        $constraints['1: check'][2][0]->columnNames(null);
        $constraints['1: check'][2][0]->expression('"C_check" <> \'\'');
        $constraints['1: unique'][2][0]->name(AnyValue::getInstance());
        $constraints['1: index'][2][1]->name(AnyValue::getInstance());

        $constraints['2: primary key'][2]->name(null);
        $constraints['2: unique'][2][0]->name(AnyValue::getInstance());
        $constraints['2: index'][2][2]->name(AnyValue::getInstance());

        $constraints['3: foreign key'][2][0]->name(null);
        $constraints['3: index'][2] = [];

        $constraints['4: primary key'][2]->name(null);
        $constraints['4: unique'][2][0]->name(AnyValue::getInstance());

        return $constraints;
    }

    public function pdoAttributes(): array
    {
        $baseSchemaProvider = new BaseSchemaProvider();

        return $baseSchemaProvider->pdoAttributes();
    }

    public function quoteTableName(): array
    {
        return [
            ['test', '`test`'],
            ['test.test', '`test`.`test`'],
            ['test.test.test', '`test`.`test`'],
            ['`test`', '`test`'],
            ['`test`.`test`', '`test`.`test`'],
            ['test.`test`.test', '`test`.`test`'],
        ];
    }

    public function quoterTableParts(): array
    {
        return [
            ['animal', 'animal',],
            ['dbo.animal', 'animal', 'dbo'],
            ['`dbo`.`animal`', 'animal', 'dbo'],
            ['`other`.`animal2`', 'animal2', 'other'],
            ['other.`animal2`', 'animal2', 'other'],
            ['other.animal2', 'animal2', 'other'],
            ['catalog.other.animal2', 'animal2', 'other'],
        ];
    }

    public function tableSchemaCachePrefixes(): array
    {
        $baseSchemaProvider = new BaseSchemaProvider();

        return $baseSchemaProvider->tableSchemaCachePrefixes();
    }
}
