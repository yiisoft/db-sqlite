<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\PDO;

use PDOException;
use Throwable;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Command\Command;
use Yiisoft\Db\Connection\ConnectionPDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Query\QueryBuilderInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Sqlite\SqlToken;
use Yiisoft\Db\Sqlite\SqlTokenizer;
use Yiisoft\Strings\StringHelper;

use function array_pop;
use function count;
use function ltrim;
use function preg_match_all;
use function strpos;

final class CommandPDOSqlite extends Command
{
    public function __construct(private ConnectionPDOInterface $db, QueryCache $queryCache)
    {
        parent::__construct($queryCache);
    }

    public function queryBuilder(): QueryBuilderInterface
    {
        return $this->db->getQueryBuilder();
    }

    public function prepare(?bool $forRead = null): void
    {
        if (isset($this->pdoStatement)) {
            $this->bindPendingParams();

            return;
        }

        $sql = $this->getSql() ?? '';

        if ($this->db->getTransaction()) {
            /** master is in a transaction. use the same connection. */
            $forRead = false;
        }

        if ($forRead || ($forRead === null && $this->db->getSchema()->isReadQuery($sql))) {
            $pdo = $this->db->getSlavePdo();
        } else {
            $pdo = $this->db->getMasterPdo();
        }

        try {
            $this->pdoStatement = $pdo?->prepare($sql);
            $this->bindPendingParams();
        } catch (PDOException $e) {
            $message = $e->getMessage() . "\nFailed to prepare SQL: $sql";
            $errorInfo = $e->errorInfo ?? null;

            throw new Exception($message, $errorInfo, $e);
        }
    }

    /**
     * Executes the SQL statement.
     *
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     *
     * @throws Exception|Throwable execution failed.
     *
     * @return int number of rows affected by the execution.
     */
    public function execute(): int
    {
        $sql = $this->getSql() ?? '';

        /** @var array<string, string> */
        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false) {
            return parent::execute();
        }

        $result = 0;

        /** @psalm-var array<array-key, array<array-key, string|array>> $statements */
        foreach ($statements as $statement) {
            [$statementSql, $statementParams] = $statement;
            $statementSql = is_string($statementSql) ? $statementSql : '';
            $statementParams = is_array($statementParams) ? $statementParams : [];
            $this->setSql($statementSql)->bindValues($statementParams);
            $result = parent::execute();
        }

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    protected function getCacheKey(string $method, ?int $fetchMode, string $rawSql): array
    {
        return [
            __CLASS__,
            $method,
            $fetchMode,
            $this->db->getDriver()->getDsn(),
            $this->db->getDriver()->getUsername(),
            $rawSql,
        ];
    }

    protected function internalExecute(?string $rawSql): void
    {
        $attempt = 0;

        while (true) {
            try {
                if (
                    ++$attempt === 1
                    && $this->isolationLevel !== null
                    && $this->db->getTransaction() === null
                ) {
                    $this->db->transaction(fn (?string $rawSql) => $this->internalExecute($rawSql), $this->isolationLevel);
                } else {
                    $this->pdoStatement?->execute();
                }
                break;
            } catch (PDOException $e) {
                $rawSql = $rawSql ?: $this->getRawSql();
                $e = $this->db->getSchema()->convertException($e, $rawSql);

                if ($this->retryHandler === null || !($this->retryHandler)($e, $attempt)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Performs the actual DB query of a SQL statement.
     *
     * @param string $method method of PDOStatement to be called
     * @param array|int|null $fetchMode the result fetch mode.
     * Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php) for valid fetch
     * modes. If this parameter is null, the value set in {@see fetchMode} will be used.
     *
     * @throws Exception|Throwable if the query causes any problem.
     *
     * @return mixed the method execution result.
     */
    protected function queryInternal(string $method, array|int $fetchMode = null): mixed
    {
        $sql = $this->getSql() ?? '';

        /** @var array<string, string> */
        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false || $statements === []) {
            return parent::queryInternal($method, $fetchMode);
        }

        [$lastStatementSql, $lastStatementParams] = array_pop($statements);

        /**
         * @psalm-var array<array-key, array> $statements
         */
        foreach ($statements as $statement) {
            /**
             * @var string $statementSql
             * @var array $statementParams
             */
            [$statementSql, $statementParams] = $statement;
            $this->setSql($statementSql)->bindValues($statementParams);
            parent::execute();
        }

        $this->setSql($lastStatementSql)->bindValues($lastStatementParams);

        /** @var string */
        $result = parent::queryInternal($method, $fetchMode);

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    /**
     * Splits the specified SQL codes into individual SQL statements and returns them or `false` if there's a single
     * statement.
     *
     * @param string $sql
     * @param array $params
     *
     * @throws InvalidArgumentException
     *
     * @return array|bool (array|string)[][]|bool
     *
     * @psalm-param array<string, string> $params
     * @psalm-return false|list<array{0: string, 1: array}>
     */
    private function splitStatements(string $sql, array $params): bool|array
    {
        $semicolonIndex = strpos($sql, ';');

        if ($semicolonIndex === false || $semicolonIndex === StringHelper::byteLength($sql) - 1) {
            return false;
        }

        $tokenizer = new SqlTokenizer($sql);

        $codeToken = $tokenizer->tokenize();

        if (count($codeToken->getChildren()) === 1) {
            return false;
        }

        $statements = [];

        foreach ($codeToken->getChildren() as $statement) {
            $statements[] = [$statement->getSql(), $this->extractUsedParams($statement, $params)];
        }

        return $statements;
    }

    /**
     * Returns named bindings used in the specified statement token.
     *
     * @param SqlToken $statement
     * @param array $params
     *
     * @return array
     *
     * @psalm-param array<string, string> $params
     */
    private function extractUsedParams(SqlToken $statement, array $params): array
    {
        preg_match_all('/(?P<placeholder>[:][a-zA-Z0-9_]+)/', $statement->getSql(), $matches, PREG_SET_ORDER);

        $result = [];

        foreach ($matches as $match) {
            $phName = ltrim($match['placeholder'], ':');
            if (isset($params[$phName])) {
                $result[$phName] = $params[$phName];
            } elseif (isset($params[':' . $phName])) {
                $result[':' . $phName] = $params[':' . $phName];
            }
        }

        return $result;
    }
}
