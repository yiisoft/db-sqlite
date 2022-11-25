<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\QueryBuilder\QueryBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Provider\BaseCommandProvider;

final class CommandProvider
{
    use TestTrait;

    public function batchInsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        $batchInsertSql = $baseCommandProvider->batchInsert($this->getConnection());
        unset($batchInsertSql['wrongBehavior']);

        return $batchInsertSql;
    }

    public function createIndex(): array
    {
        return [
            ['test_idx_constraint', 'test_idx', ['int1']],
            ['test_idx_constraint', 'test_idx', ['int1', 'int2']],
            ['test_idx_constraint', 'test_idx', ['int1'], QueryBuilder::INDEX_UNIQUE],
            ['test_idx_constraint', 'test_idx', ['int1', 'int2'], QueryBuilder::INDEX_UNIQUE],
        ];
    }

    public function update(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->update($this->getConnection());
    }

    public function upsert(): array
    {
        $baseCommandProvider = new BaseCommandProvider();

        return $baseCommandProvider->upsert($this->getConnection());
    }
}
