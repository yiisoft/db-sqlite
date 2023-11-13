<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use Throwable;
use Yiisoft\Db\Driver\Pdo\AbstractPdoCommand;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;

use function array_pop;
use function count;
use function ltrim;
use function preg_match_all;
use function strpos;

/**
 * Implements a database command that can be executed with a PDO (PHP Data Object) database connection for SQLite
 * Server.
 */
final class Command extends AbstractPdoCommand
{
    public function insertWithReturningPks(string $table, array $columns): bool|array
    {
        $params = [];
        $sql = $this->db->getQueryBuilder()->insert($table, $columns, $params);
        $this->setSql($sql)->bindValues($params);

        if (!$this->execute()) {
            return false;
        }

        $tableSchema = $this->db->getSchema()->getTableSchema($table);
        $tablePrimaryKeys = $tableSchema?->getPrimaryKey() ?? [];

        $result = [];
        foreach ($tablePrimaryKeys as $name) {
            if ($tableSchema?->getColumn($name)?->isAutoIncrement()) {
                $result[$name] = $this->db->getLastInsertID((string) $tableSchema?->getSequenceName());
                continue;
            }

            $result[$name] = $columns[$name] ?? $tableSchema?->getColumn($name)?->getDefaultValue();
        }

        return $result;
    }

    public function showDatabases(): array
    {
        $sql = <<<SQL
        SELECT name FROM pragma_database_list;
        SQL;

        return $this->setSql($sql)->queryColumn();
    }

    /**
     * Executes the SQL statement.
     *
     * This method should only be used for executing a non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     *
     * @throws Exception
     * @throws Throwable The execution failed.
     *
     * @return int Number of rows affected by the execution.
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
            [$statementSql, $statementParams] = $statement;
            $this->setSql($statementSql)->bindValues($statementParams);
            $result = parent::execute();
        }

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    /**
     * Performs the actual DB query of an SQL statement.
     *
     * @param int $queryMode Return results as DataReader
     *
     * @throws Exception
     * @throws Throwable If the query causes any problem.
     *
     * @return mixed The method execution result.
     */
    protected function queryInternal(int $queryMode): mixed
    {
        $sql = $this->getSql();

        $params = $this->params;

        $statements = $this->splitStatements($sql, $params);

        if ($statements === false || $statements === []) {
            return parent::queryInternal($queryMode);
        }

        [$lastStatementSql, $lastStatementParams] = array_pop($statements);

        foreach ($statements as $statement) {
            [$statementSql, $statementParams] = $statement;
            $this->setSql($statementSql)->bindValues($statementParams);
            parent::execute();
        }

        $this->setSql($lastStatementSql)->bindValues($lastStatementParams);

        $result = parent::queryInternal($queryMode);

        $this->setSql($sql)->bindValues($params);

        return $result;
    }

    /**
     * Splits the specified SQL into individual SQL statements and returns them or `false` if there's a single
     * statement.
     *
     * @param string $sql SQL to split.
     *
     * @throws InvalidArgumentException
     *
     * @return array|bool List of SQL statements or `false` if there's a single statement.
     *
     * @psalm-return false|list<array{0: string, 1: array}>
     */
    private function splitStatements(string $sql, array $params): bool|array
    {
        $semicolonIndex = strpos($sql, ';');

        if ($semicolonIndex === false || $semicolonIndex === mb_strlen($sql, '8bit') - 1) {
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
     */
    private function extractUsedParams(SqlToken $statement, array $params): array
    {
        preg_match_all('/(?P<placeholder>:\w+)/', $statement->getSql(), $matches, PREG_SET_ORDER);

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
