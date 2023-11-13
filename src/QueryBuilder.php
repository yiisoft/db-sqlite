<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\QueryBuilder\AbstractQueryBuilder;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;

/**
 * Implements the SQLite Server specific query builder.
 */
final class QueryBuilder extends AbstractQueryBuilder
{
    /**
     * @var string[] Mapping from abstract column types (keys) to physical column types (values).
     */
    protected array $typeMap = [
        SchemaInterface::TYPE_PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        SchemaInterface::TYPE_UPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        SchemaInterface::TYPE_BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        SchemaInterface::TYPE_UBIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        SchemaInterface::TYPE_CHAR => 'char(1)',
        SchemaInterface::TYPE_STRING => 'varchar(255)',
        SchemaInterface::TYPE_TEXT => 'text',
        SchemaInterface::TYPE_TINYINT => 'tinyint',
        SchemaInterface::TYPE_SMALLINT => 'smallint',
        SchemaInterface::TYPE_INTEGER => 'integer',
        SchemaInterface::TYPE_BIGINT => 'bigint',
        SchemaInterface::TYPE_FLOAT => 'float',
        SchemaInterface::TYPE_DOUBLE => 'double',
        SchemaInterface::TYPE_DECIMAL => 'decimal(10,0)',
        SchemaInterface::TYPE_DATETIME => 'datetime',
        SchemaInterface::TYPE_TIMESTAMP => 'timestamp',
        SchemaInterface::TYPE_TIME => 'time',
        SchemaInterface::TYPE_DATE => 'date',
        SchemaInterface::TYPE_BINARY => 'blob',
        SchemaInterface::TYPE_BOOLEAN => 'boolean',
        SchemaInterface::TYPE_MONEY => 'decimal(19,4)',
        SchemaInterface::TYPE_UUID => 'blob(16)',
        SchemaInterface::TYPE_UUID_PK => 'blob(16) PRIMARY KEY',
        SchemaInterface::TYPE_JSON => 'json',
    ];

    public function __construct(QuoterInterface $quoter, SchemaInterface $schema)
    {
        $ddlBuilder = new DDLQueryBuilder($this, $quoter, $schema);
        $dmlBuilder = new DMLQueryBuilder($this, $quoter, $schema);
        $dqlBuilder = new DQLQueryBuilder($this, $quoter);
        parent::__construct($quoter, $schema, $ddlBuilder, $dmlBuilder, $dqlBuilder);
    }
}
