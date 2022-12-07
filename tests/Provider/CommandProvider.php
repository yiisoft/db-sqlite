<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;
use Yiisoft\Db\Tests\Provider\AbstractCommandProvider;

final class CommandProvider extends AbstractCommandProvider
{
    use TestTrait;

    public function batchInsert(): array
    {
        $batchInsertSql = parent::batchInsert();
        unset($batchInsertSql['wrongBehavior']);

        return $batchInsertSql;
    }
}
