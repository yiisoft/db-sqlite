<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use function array_pop;
use function count;
use function ltrim;
use function preg_match_all;
use function strpos;

use Throwable;
use Yiisoft\Db\Command\Command as BaseCommand;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Strings\StringHelper;

final class Command extends BaseCommand
{
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

        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false) {
            return parent::execute();
        }

        $result = 0;

        foreach ($statements as $statement) {
            /** @var array $statement */
            [$statementSql, $statementParams] = $statement;
            $this->setSql($statementSql)->bindValues($statementParams);
            $result = parent::execute();
        }

        $this->setSql($sql)->bindValues($params);

        return $result;
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
    protected function queryInternal(string $method, $fetchMode = null)
    {
        $sql = $this->getSql();

        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false) {
            return parent::queryInternal($method, $fetchMode);
        }

        /**
         * @psalm-suppress InvalidArrayOffset
         *
         * @var array $statements
         */
        [$lastStatementSql, $lastStatementParams] = array_pop($statements);

        foreach ($statements as $statement) {
            [$statementSql, $statementParams] = $statement;
            $this->setSql($statementSql)->bindValues($statementParams);
            parent::execute();
        }

        $this->setSql($lastStatementSql)->bindValues($lastStatementParams);

        $result = parent::queryInternal($method, $fetchMode);

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    /**
     * Splits the specified SQL code into individual SQL statements and returns them or `false` if there's a single
     * statement.
     *
     * @param string $sql
     * @param array $params
     *
     * @throws InvalidArgumentException
     *
     * @return array|bool (array|string)[][]|bool
     *
     * @psalm-return false|list<array{0: string, 1: array}>
     */
    private function splitStatements(string $sql, array $params)
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
     */
    private function extractUsedParams(SqlToken $statement, $params): array
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
