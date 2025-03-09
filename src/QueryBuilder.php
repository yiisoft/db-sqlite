<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Connection\ServerInfoInterface;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\AbstractQueryBuilder;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Column\ColumnDefinitionBuilder;

use function bin2hex;

/**
 * Implements the SQLite Server specific query builder.
 */
final class QueryBuilder extends AbstractQueryBuilder
{
    public function __construct(QuoterInterface $quoter, SchemaInterface $schema, ServerInfoInterface $serverInfo)
    {
        parent::__construct(
            $quoter,
            $schema,
            $serverInfo,
            new DDLQueryBuilder($this, $quoter, $schema),
            new DMLQueryBuilder($this, $quoter, $schema),
            new DQLQueryBuilder($this, $quoter),
            new ColumnDefinitionBuilder($this),
        );
    }

    /**
     * @throws NotSupportedException SQLite doesn't support cascade drop table.
     */
    public function dropTable(string $table, bool $ifExists = false, bool $cascade = false): string
    {
        if ($cascade) {
            throw new NotSupportedException('SQLite doesn\'t support cascade drop table.');
        }
        return parent::dropTable($table, $ifExists, false);
    }

    protected function prepareBinary(string $binary): string
    {
        return "x'" . bin2hex($binary) . "'";
    }
}
