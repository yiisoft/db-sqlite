<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Provider;

final class QuoterProvider
{
    /**
     * @return string[][]
     */
    public function columnNames(): array
    {
        return [
            ['*', '*'],
            ['table.*', '`table`.*'],
            ['`table`.*', '`table`.*'],
            ['table.column', '`table`.`column`'],
            ['`table`.column', '`table`.`column`'],
            ['table.`column`', '`table`.`column`'],
            ['`table`.`column`', '`table`.`column`'],
        ];
    }

    /**
     * @return string[][]
     */
    public function simpleColumnNames(): array
    {
        return [
            ['test', '`test`', 'test'],
            ['`test`', '`test`', 'test'],
            ['*', '*', '*'],
        ];
    }

    /**
     * @return string[][]
     */
    public function simpleTableNames(): array
    {
        return [
            ['test', '`test`', ],
            ['te\'st', '`te\'st`', ],
            ['te"st', '`te"st`', ],
            ['current-table-name', '`current-table-name`', ],
            ['`current-table-name`', '`current-table-name`', ],
        ];
    }

    public function unquoteSimpleColumnName(): array
    {
        return [
            ['`test`', 'test'],
            ['`te\'st`', 'te\'st'],
            ['`te"st`', 'te"st'],
            ['`current-table-name`', 'current-table-name'],
        ];
    }

    public function unquoteSimpleTableName(): array
    {
        return [
            ['`test`', 'test'],
            ['`te\'st`', 'te\'st'],
            ['`te"st`', 'te"st'],
            ['current-table-name', 'current-table-name'],
            ['`current-table-name`', 'current-table-name'],
        ];
    }
}
