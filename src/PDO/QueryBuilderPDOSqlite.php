<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\PDO;

use Generator;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionBuilder;
use Yiisoft\Db\Expression\ExpressionBuilderInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\Conditions\InCondition;
use Yiisoft\Db\Query\Conditions\LikeCondition;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryBuilder;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\Schema\ColumnSchemaBuilder;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\Builder\InConditionBuilder;
use Yiisoft\Db\Sqlite\Builder\LikeConditionBuilder;
use Yiisoft\Db\Sqlite\DDLQueryBuilder;
use Yiisoft\Db\Sqlite\DMLQueryBuilder;

use function array_filter;
use function array_merge;
use function implode;
use function trim;

final class QueryBuilderPDOSqlite extends QueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     *
     * @psalm-var string[] $typeMap
     */
    protected array $typeMap = [
        Schema::TYPE_PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_UBIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
        Schema::TYPE_CHAR => 'char(1)',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'text',
        Schema::TYPE_TINYINT => 'tinyint',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'integer',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DOUBLE => 'double',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'datetime',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'blob',
        Schema::TYPE_BOOLEAN => 'boolean',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];
    private DDLQueryBuilder $ddlBuilder;
    private DMLQueryBuilder $dmlBuilder;

    public function __construct(
        private CommandInterface $command,
        private QuoterInterface $quoter,
        private SchemaInterface $schema
    ) {
        $this->ddlBuilder = new DDLQueryBuilder($this);
        $this->dmlBuilder = new DMLQueryBuilder($this);
        parent::__construct($quoter, $schema, $this->ddlBuilder, $this->dmlBuilder);
    }

    public function addForeignKey(
        string $name,
        string $table,
        array|string $columns,
        string $refTable,
        array|string $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): string {
        return $this->ddlBuilder->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    /**
     * @throws NotSupportedException
     */
    public function addPrimaryKey(string $name, string $table, array|string $columns): string
    {
        return $this->ddlBuilder->addPrimaryKey($name, $table, $columns);
    }

    /**
     * @throws NotSupportedException
     */
    public function addUnique(string $name, string $table, array|string $columns): string
    {
        return $this->ddlBuilder->addUnique($name, $table, $columns);
    }

    /**
     * @throws NotSupportedException
     */
    public function alterColumn(string $table, string $column, string|ColumnSchemaBuilder $type): string
    {
        return $this->ddlBuilder->alterColumn($table, $column, $type);
    }

    public function batchInsert(string $table, array $columns, iterable|Generator $rows, array &$params = []): string
    {
        return $this->dmlBuilder->batchInsert($table, $columns, $rows, $params);
    }

    public function build(QueryInterface $query, array $params = []): array
    {
        $query = $query->prepare($this);

        $params = empty($params) ? $query->getParams() : array_merge($params, $query->getParams());

        $clauses = [
            $this->buildSelect($query->getSelect(), $params, $query->getDistinct(), $query->getSelectOption()),
            $this->buildFrom($query->getFrom(), $params),
            $this->buildJoin($query->getJoin(), $params),
            $this->buildWhere($query->getWhere(), $params),
            $this->buildGroupBy($query->getGroupBy()),
            $this->buildHaving($query->getHaving(), $params),
        ];

        $orderBy = $query->getOrderBy();
        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->buildOrderByAndLimit($sql, $orderBy, $query->getLimit(), $query->getOffset());

        if (!empty($orderBy)) {
            /** @psalm-var array<string|ExpressionInterface> $orderBy */
            foreach ($orderBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $groupBy = $query->getGroupBy();

        if (!empty($groupBy)) {
            /** @psalm-var array<string|ExpressionInterface> $groupBy */
            foreach ($groupBy as $expression) {
                if ($expression instanceof ExpressionInterface) {
                    $this->buildExpression($expression, $params);
                }
            }
        }

        $union = $this->buildUnion($query->getUnion(), $params);

        if ($union !== '') {
            $sql = "$sql$this->separator$union";
        }

        $with = $this->buildWithQueries($query->getWithQueries(), $params);

        if ($with !== '') {
            $sql = "$with$this->separator$sql";
        }

        return [$sql, $params];
    }

    public function buildLimit(Expression|int|null $limit, Expression|int|null $offset): string
    {
        $sql = '';

        if ($this->hasLimit($limit)) {
            $sql = "LIMIT $limit";
            if ($this->hasOffset($offset)) {
                $sql .= " OFFSET $offset";
            }
        } elseif ($this->hasOffset($offset)) {
            /**
             * limit is not optional in SQLite.
             *
             * {@see http://www.sqlite.org/syntaxdiagrams.html#select-stmt}
             */
            $sql = "LIMIT 9223372036854775807 OFFSET $offset"; // 2^63-1
        }

        return $sql;
    }

    public function buildUnion(array $unions, array &$params = []): string
    {
        if (empty($unions)) {
            return '';
        }

        $result = '';

        /** @psalm-var array<array-key, array{query: Query|null, all: bool}> $unions */
        foreach ($unions as $i => $union) {
            $query = $union['query'];
            if ($query instanceof Query) {
                [$unions[$i]['query'], $params] = $this->build($query, $params);
            }

            $result .= ' UNION ' . ($union['all'] ? 'ALL ' : '') . ' ' . (string) $unions[$i]['query'];
        }

        return trim($result);
    }

    public function checkIntegrity(string $schema = '', string $table = '', bool $check = true): string
    {
        return $this->ddlBuilder->checkIntegrity($schema, $table, $check);
    }

    public function createIndex(string $name, string $table, $columns, bool $unique = false): string
    {
        return $this->ddlBuilder->createIndex($name, $table, $columns, $unique);
    }

    public function command(): CommandInterface
    {
        return $this->command;
    }

    /**
     * @throws NotSupportedException
     */
    public function dropColumn(string $table, string $column): string
    {
        return $this->ddlBuilder->dropColumn($table, $column);
    }

    /**
     * @throws NotSupportedException
     */
    public function dropForeignKey(string $name, string $table): string
    {
        return $this->ddlBuilder->dropForeignKey($name, $table);
    }

    public function dropIndex(string $name, string $table): string
    {
        return $this->ddlBuilder->dropIndex($name, $table);
    }

    /**
     * @throws NotSupportedException
     */
    public function dropPrimaryKey(string $name, string $table): string
    {
        return $this->ddlBuilder->dropPrimaryKey($name, $table);
    }

    /**
     * @throws NotSupportedException
     */
    public function dropUnique(string $name, string $table): string
    {
        return $this->ddlBuilder->dropUnique($name, $table);
    }

    public function quoter(): QuoterInterface
    {
        return $this->quoter;
    }

    public function schema(): SchemaInterface
    {
        return $this->schema;
    }

    /**
     * @throws NotSupportedException
     */
    public function renameColumn(string $table, string $oldName, string $newName): string
    {
        return $this->ddlBuilder->renameColumn($table, $oldName, $newName);
    }

    public function renameTable(string $oldName, string $newName): string
    {
        return $this->ddlBuilder->renameTable($oldName, $newName);
    }

    public function truncateTable(string $table): string
    {
        return $this->ddlBuilder->truncateTable($table);
    }

    /**
     * Contains array of default expression builders. Extend this method and override it, if you want to change default
     * expression builders for this query builder.
     *
     * @return array
     *
     * See {@see ExpressionBuilder} docs for details.
     *
     * @psalm-return array<string, class-string<ExpressionBuilderInterface>>
     */
    protected function defaultExpressionBuilders(): array
    {
        return array_merge(parent::defaultExpressionBuilders(), [
            LikeCondition::class => LikeConditionBuilder::class,
            InCondition::class => InConditionBuilder::class,
        ]);
    }
}
