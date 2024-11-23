<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Constant\PseudoType;
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
    /**
     * @var string[] Mapping from abstract column types (keys) to physical column types (values).
     */
    protected array $typeMap = [
        PseudoType::PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        PseudoType::UPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        PseudoType::BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        PseudoType::UBIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        ColumnType::CHAR => 'char(1)',
        ColumnType::STRING => 'varchar(255)',
        ColumnType::TEXT => 'text',
        ColumnType::TINYINT => 'tinyint',
        ColumnType::SMALLINT => 'smallint',
        ColumnType::INTEGER => 'integer',
        ColumnType::BIGINT => 'bigint',
        ColumnType::FLOAT => 'float',
        ColumnType::DOUBLE => 'double',
        ColumnType::DECIMAL => 'decimal(10,0)',
        ColumnType::DATETIME => 'datetime',
        ColumnType::TIMESTAMP => 'timestamp',
        ColumnType::TIME => 'time',
        ColumnType::DATE => 'date',
        ColumnType::BINARY => 'blob',
        ColumnType::BOOLEAN => 'boolean',
        ColumnType::MONEY => 'decimal(19,4)',
        ColumnType::UUID => 'blob(16)',
        PseudoType::UUID_PK => 'blob(16) PRIMARY KEY',
        ColumnType::JSON => 'json',
    ];

    public function __construct(QuoterInterface $quoter, SchemaInterface $schema)
    {
        $ddlBuilder = new DDLQueryBuilder($this, $quoter, $schema);
        $dmlBuilder = new DMLQueryBuilder($this, $quoter, $schema);
        $dqlBuilder = new DQLQueryBuilder($this, $quoter);
        $columnDefinitionBuilder = new ColumnDefinitionBuilder($this);

        parent::__construct($quoter, $schema, $ddlBuilder, $dmlBuilder, $dqlBuilder, $columnDefinitionBuilder);
    }

    protected function prepareBinary(string $binary): string
    {
        return "x'" . bin2hex($binary) . "'";
    }
}
