<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Tests\Provider\AbstractColumnSchemaBuilderProvider;

final class ColumnSchemaBuilderProvider extends AbstractColumnSchemaBuilderProvider
{
    public function types(): array
    {
        $types = parent::types();

        $types['integer-1'] = ['integer UNSIGNED', Schema::TYPE_INTEGER, null, [['unsigned']]];
        $types['integer-2'] = ['integer(10) UNSIGNED', Schema::TYPE_INTEGER, 10, [['unsigned']]];
        $types['integer-3'] = ['integer(10)', Schema::TYPE_INTEGER, 10, [['comment', 'test']]];

        return $types;
    }
}
