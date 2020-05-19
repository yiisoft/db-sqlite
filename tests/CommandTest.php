<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Tests;

use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Tests\CommandTest as AbstractCommandTest;

class CommandTest extends AbstractCommandTest
{
    protected ?string $driverName = 'sqlite';

    public function testAutoQuoting(): void
    {
        $db = $this->getConnection(false);

        $sql = 'SELECT [[id]], [[t.name]] FROM {{customer}} t';

        $command = $db->createCommand($sql);

        $this->assertEquals('SELECT `id`, `t`.`name` FROM `customer` t', $command->getSql());
    }

    /**
     * @dataProvider upsertProvider
     *
     * @param array $firstData
     * @param array $secondData
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function testUpsert(array $firstData, array $secondData)
    {
        if (version_compare($this->getConnection(false)->getServerVersion(), '3.8.3', '<')) {
            $this->markTestSkipped('SQLite < 3.8.3 does not support "WITH" keyword.');

            return;
        }

        parent::testUpsert($firstData, $secondData);
    }

    public function testAddDropPrimaryKey(): void
    {
        $this->markTestSkipped('SQLite does not support adding/dropping primary keys.');
    }

    public function testAddDropCheck(): void
    {
        $this->markTestSkipped('SQLite does not support adding/dropping check constraints.');
    }

    public function testMultiStatementSupport(): void
    {
        $db = $this->getConnection(false, true);

        $sql = <<<'SQL'
DROP TABLE IF EXISTS {{T_multistatement}};
CREATE TABLE {{T_multistatement}} (
    [[intcol]] INTEGER,
    [[textcol]] TEXT
);
INSERT INTO {{T_multistatement}} VALUES(41, :val1);
INSERT INTO {{T_multistatement}} VALUES(42, :val2);
SQL;

        $db->createCommand($sql, [
            'val1' => 'foo',
            'val2' => 'bar',
        ])->execute();

        $this->assertSame([
            [
                'intcol' => '41',
                'textcol' => 'foo',
            ],
            [
                'intcol' => '42',
                'textcol' => 'bar',
            ],
        ], $db->createCommand('SELECT * FROM {{T_multistatement}}')->queryAll());

        $sql = <<<'SQL'
UPDATE {{T_multistatement}} SET [[intcol]] = :newInt WHERE [[textcol]] = :val1;
DELETE FROM {{T_multistatement}} WHERE [[textcol]] = :val2;
SELECT * FROM {{T_multistatement}}
SQL;

        $this->assertSame([
            [
                'intcol' => '410',
                'textcol' => 'foo',
            ],
        ], $db->createCommand($sql, [
            'newInt' => 410,
            'val1' => 'foo',
            'val2' => 'bar',
        ])->queryAll());
    }

    public function batchInsertSqlProvider(): array
    {
        $parent = parent::batchInsertSqlProvider();
        unset($parent['wrongBehavior']); /** Produces SQL syntax error: General error: 1 near ".": syntax error */

        return $parent;
    }

    public function testForeingKeyException(): void
    {
        $db = $this->getConnection(false);

        $db->createCommand('PRAGMA foreign_keys = ON')->execute();

        $tableMaster = 'departments';
        $tableRelation = 'students';
        $name = 'test_fk_constraint';

        /** @var \Yiisoft\Db\Sqlite\Schema $schema */
        $schema = $db->getSchema();

        if ($schema->getTableSchema($tableRelation) !== null) {
            $db->createCommand()->dropTable($tableRelation)->execute();
        }

        if ($schema->getTableSchema($tableMaster) !== null) {
            $db->createCommand()->dropTable($tableMaster)->execute();
        }

        $db->createCommand()->createTable($tableMaster, [
            'department_id' => 'integer not null primary key autoincrement',
            'department_name' => 'nvarchar(50) null',
        ])->execute();

        $db->createCommand()->createTable($tableRelation, [
            'student_id' => 'integer primary key autoincrement not null',
            'student_name' => 'nvarchar(50) null',
            'department_id' => 'integer not null',
            'dateOfBirth' => 'date null'
        ])->execute();

        $db->createCommand()->addForeignKey(
            $name,
            $tableRelation,
            ['Department_id'],
            $tableMaster,
            ['Department_id']
        )->execute();

        $db->createCommand(
            "INSERT INTO departments VALUES (1, 'IT')"
        )->execute();

        $db->createCommand(
            'INSERT INTO students(student_name, department_id) VALUES ("John", 1);'
        )->execute();

        $this->expectException(IntegrityException::class);
        $this->expectExceptionMessage(
            <<<EOD
SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed
The SQL being executed was: INSERT INTO students(student_name, department_id) VALUES ("Samdark", 5)
EOD
        );

        $db->createCommand(
            'INSERT INTO students(student_name, department_id) VALUES ("Samdark", 5);'
        )->execute();
    }
}
