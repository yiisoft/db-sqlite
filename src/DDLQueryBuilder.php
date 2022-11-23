<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\QueryBuilder\DDLQueryBuilder as AbstractDDLQueryBuilder;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\ColumnSchemaBuilder;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;

use function count;

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
     * @throws NotSupportedException
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
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
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function addPrimaryKey(string $name, string $table, array|string $columns): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function addUnique(string $name, string $table, array|string $columns): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function alterColumn(string $table, string $column, ColumnSchemaBuilder|string $type): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    public function checkIntegrity(string $schema = '', string $table = '', bool $check = true): string
    {
        return 'PRAGMA foreign_keys=' . (int) $check;
    }

    /**
     * @throws Exception|InvalidArgumentException
     */
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
     * @throws NotSupportedException
     */
    public function dropCheck(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function dropColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function dropCommentFromTable(string $table): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function dropForeignKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    public function dropIndex(string $name, string $table): string
    {
        return 'DROP INDEX ' . $this->quoter->quoteTableName($name);
    }

    /**
     * @throws NotSupportedException
     */
    public function dropPrimaryKey(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function dropUnique(string $name, string $table): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
    }

    /**
     * @throws NotSupportedException
     */
    public function renameColumn(string $table, string $oldName, string $newName): string
    {
        throw new NotSupportedException(__METHOD__ . '()' . ' is not supported by SQLite.');
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
