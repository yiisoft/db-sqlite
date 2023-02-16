<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;

final class CommandProvider extends \Yiisoft\Db\Tests\Provider\CommandProvider
{
    use TestTrait;

    public function batchInsert(): array
    {
        return parent::batchInsert();
    }
}
