<?php

declare(strict_types=1);

use Yiisoft\Db\Sqlite\Column\ColumnDefinitionParser;
use Yiisoft\Db\Syntax\ColumnDefinitionParserInterface;
use Yiisoft\Db\Tests\Common\CommonColumnDefinitionParserTest;

/**
 * @group sqlite
 */
final class ColumnDefinitionParserTest extends CommonColumnDefinitionParserTest
{
    protected function createColumnDefinitionParser(): ColumnDefinitionParserInterface
    {
        return new ColumnDefinitionParser();
    }
}
