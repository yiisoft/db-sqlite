<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\AbstractDDLQueryBuilder;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\Builder\ColumnInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;

use function count;

/**
 * Implements a (Data Definition Language) SQL statements for SQLite Server.
 */
final class DDLQueryBuilder extends AbstractDDLQueryBuilder
{
    public function __construct(
        private QueryBuilderInterface $queryBuilder,
        private QuoterInterface $quoter,
        SchemaInterface $schema
    ) {
        parent::__construct($queryBuilder, $quoter, $schema);
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addDefaultValue(string $name, string $table, string $column, mixed $value): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addForeignKey(
        string $name,
        string $table,
        array|string $columns,
        string $refTable,
        array|string $refColumns,
        string $delete = null,
        string $update = null
    ): string {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addPrimaryKey(string $name, string $table, array|string $columns): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function addUnique(string $name, string $table, array|string $columns): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function alterColumn(string $table, string $column, ColumnInterface|string $type): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    public function checkIntegrity(string $schema = '', string $table = '', bool $check = true): string
    {
        return 'PRAGMA foreign_keys=' . (int) $check;
    }

    public function createIndex(
        string $name,
        string $table,
        array|string $columns,
        string $indexType = null,
        string $indexMethod = null
    ): string {
        $tableParts = explode('.', $table);

        $schema = null;
        if (count($tableParts) === 2) {
            [$schema, $table] = $tableParts;
        }

        return 'CREATE ' . ($indexType ? ($indexType . ' ') : '') . 'INDEX '
            . $this->quoter->quoteTableName(($schema ? $schema . '.' : '') . $name)
            . ' ON '
            . $this->quoter->quoteTableName($table)
            . ' (' . $this->queryBuilder->buildColumns($columns) . ')';
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropCheck(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropCommentFromTable(string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropDefaultValue(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropForeignKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    public function dropIndex(string $name, string $table): string
    {
        return 'DROP INDEX ' . $this->quoter->quoteTableName($name);
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropPrimaryKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function dropUnique(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException SQLite doesn't support this method.
     */
    public function renameColumn(string $table, string $oldName, string $newName): string
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported by SQLite.');
    }

    public function renameTable(string $oldName, string $newName): string
    {
        return 'ALTER TABLE '
            . $this->quoter->quoteTableName($oldName)
            . ' RENAME TO '
            . $this->quoter->quoteTableName($newName);
    }

    public function truncateTable(string $table): string
    {
        return 'DELETE FROM ' . $this->quoter->quoteTableName($table);
    }
}
