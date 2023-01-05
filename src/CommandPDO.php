<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use PDOException;
use Throwable;
use Yiisoft\Db\Driver\PDO\AbstractCommandPDO;
use Yiisoft\Db\Driver\PDO\ConnectionPDOInterface;
use Yiisoft\Db\Exception\ConvertException;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Strings\StringHelper;

use function array_pop;
use function count;
use function ltrim;
use function preg_match_all;
use function strpos;

final class CommandPDO extends AbstractCommandPDO
{
    public function queryBuilder(): QueryBuilderInterface
    {
        return $this->db->getQueryBuilder();
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
        $sql = $this->getSql();

        /** @psalm-var array<string, string> $params */
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

    /**
     * @psalm-suppress UnusedClosureParam
     *
     * @throws Throwable
     */
    protected function internalExecute(string|null $rawSql): void
    {
        $attempt = 0;

        while (true) {
            try {
                if (
                    ++$attempt === 1
                    && $this->isolationLevel !== null
                    && $this->db->getTransaction() === null
                ) {
                    $this->db->transaction(
                        fn (ConnectionPDOInterface $db) => $this->internalExecute($rawSql),
                        $this->isolationLevel,
                    );
                } else {
                    $this->pdoStatement?->execute();
                }
                break;
            } catch (PDOException $e) {
                $rawSql = $rawSql ?: $this->getRawSql();
                $e = (new ConvertException($e, $rawSql))->run();

                if ($this->retryHandler === null || !($this->retryHandler)($e, $attempt)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Performs the actual DB query of a SQL statement.
     *
     * @param int $queryMode - return results as DataReader
     *
     * @throws Exception|Throwable if the query causes any problem.
     *
     * @return mixed the method execution result.
     */
    protected function queryInternal(int $queryMode): mixed
    {
        $sql = $this->getSql();

        /** @psalm-var array<string, string> $params */
        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false || $statements === []) {
            return parent::queryInternal($queryMode);
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

        /** @psalm-var string $result */
        $result = parent::queryInternal($queryMode);

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    /**
     * Splits the specified SQL codes into individual SQL statements and returns them or `false` if there's a single
     * statement.
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
