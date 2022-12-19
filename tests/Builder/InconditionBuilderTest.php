<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\InCondition;
use Yiisoft\Db\Sqlite\Builder\InConditionBuilder;
use Yiisoft\Db\Sqlite\Tests\Support\TestTrait;

/**
 * @group sqlite
 */
final class InconditionBuilderTest extends TestCase
{
    use TestTrait;

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function testBuildSubqueryInCondition(): void
    {
        $db = $this->getConnection();
        $inCondition = new InCondition(
            ['id'],
            'in',
            (new Query($db))->select('id')->from('users')->where(['active' => 1]),
        );

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage(
            'Yiisoft\Db\Sqlite\Builder\InConditionBuilder::buildSubqueryInCondition is not supported by SQLite.'
        );

        (new InConditionBuilder($db->getQueryBuilder()))->build($inCondition);
    }
}
