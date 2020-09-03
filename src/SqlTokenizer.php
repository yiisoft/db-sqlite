<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use function mb_strtoupper;
use function strtr;

/**
 * SqlTokenizer splits SQLite query into individual SQL tokens.
 *
 * It's used to obtain a `CHECK` constraint information from a `CREATE TABLE` SQL code.
 *
 * {@see http://www.sqlite.org/draft/tokenreq.html}
 * {@see https://sqlite.org/lang.html}
 */
final class SqlTokenizer extends BaseTokenizer
{
    /**
     * Returns whether there's a whitespace at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string.
     *
     * @param int|null $length length of the matched string.
     *
     * @return bool whether there's a whitespace at the current offset.
     */
    protected function isWhitespace(?int &$length): bool
    {
        static $whitespaces = [
            "\f" => true,
            "\n" => true,
            "\r" => true,
            "\t" => true,
            ' ' => true,
        ];

        $length = 1;

        return isset($whitespaces[$this->substring($length)]);
    }

    /**
     * Returns whether there's a commentary at the current offset.
     *
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     *
     * @param int $length length of the matched string.
     *
     * @return bool whether there's a commentary at the current offset.
     */
    protected function isComment(int &$length): bool
    {
        static $comments = [
            '--' => true,
            '/*' => true,
        ];

        $length = 2;

        if (!isset($comments[$this->substring($length)])) {
            return false;
        }

        if ($this->substring($length) === '--') {
            $length = $this->indexAfter("\n") - $this->offset;
        } else {
            $length = $this->indexAfter('*/') - $this->offset;
        }

        return true;
    }

    /**
     * Returns whether there's an operator at the current offset.
     *
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length  length of the matched string.
     * @param string|null $content optional content instead of the matched string.
     *
     * @return bool whether there's an operator at the current offset.
     */
    protected function isOperator(int &$length, ?string &$content): bool
    {
        static $operators = [
            '!=',
            '%',
            '&',
            '(',
            ')',
            '*',
            '+',
            ',',
            '-',
            '.',
            '/',
            ';',
            '<',
            '<<',
            '<=',
            '<>',
            '=',
            '==',
            '>',
            '>=',
            '>>',
            '|',
            '||',
            '~',
        ];

        return $this->startsWithAnyLongest($operators, true, $length);
    }

    /**
     * Returns whether there's an identifier at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length length of the matched string.
     * @param string|null $content optional content instead of the matched string.
     *
     * @return bool whether there's an identifier at the current offset.
     */
    protected function isIdentifier(int &$length, ?string &$content): bool
    {
        static $identifierDelimiters = [
            '"' => '"',
            '[' => ']',
            '`' => '`',
        ];

        if (!isset($identifierDelimiters[$this->substring(1)])) {
            return false;
        }

        $delimiter = $identifierDelimiters[$this->substring(1)];
        $offset = $this->offset;

        while (true) {
            $offset = $this->indexAfter($delimiter, $offset + 1);
            if ($delimiter === ']' || $this->substring(1, true, $offset) !== $delimiter) {
                break;
            }
        }
        $length = $offset - $this->offset;
        $content = $this->substring($length - 2, true, $this->offset + 1);

        if ($delimiter !== ']') {
            $content = strtr($content, ["$delimiter$delimiter" => $delimiter]);
        }

        return true;
    }

    /**
     * Returns whether there's a string literal at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length  length of the matched string.
     * @param string|null $content optional content instead of the matched string.
     *
     * @return bool whether there's a string literal at the current offset.
     */
    protected function isStringLiteral(int &$length, ?string &$content): bool
    {
        if ($this->substring(1) !== "'") {
            return false;
        }

        $offset = $this->offset;

        while (true) {
            $offset = $this->indexAfter("'", $offset + 1);
            if ($this->substring(1, true, $offset) !== "'") {
                break;
            }
        }
        $length = $offset - $this->offset;
        $content = strtr($this->substring($length - 2, true, $this->offset + 1), ["''" => "'"]);

        return true;
    }

    /**
     * Returns whether the given string is a keyword.
     *
     * The method may set `$content` to a string that will be used as a token content.
     *
     * @param string $string  string to be matched.
     * @param string|null $content optional content instead of the matched string.
     *
     * @return bool whether the given string is a keyword.
     */
    protected function isKeyword(string $string, ?string &$content): bool
    {
        static $keywords = [
            'ABORT' => true,
            'ACTION' => true,
            'ADD' => true,
            'AFTER' => true,
            'ALL' => true,
            'ALTER' => true,
            'ANALYZE' => true,
            'AND' => true,
            'AS' => true,
            'ASC' => true,
            'ATTACH' => true,
            'AUTOINCREMENT' => true,
            'BEFORE' => true,
            'BEGIN' => true,
            'BETWEEN' => true,
            'BY' => true,
            'CASCADE' => true,
            'CASE' => true,
            'CAST' => true,
            'CHECK' => true,
            'COLLATE' => true,
            'COLUMN' => true,
            'COMMIT' => true,
            'CONFLICT' => true,
            'CONSTRAINT' => true,
            'CREATE' => true,
            'CROSS' => true,
            'CURRENT_DATE' => true,
            'CURRENT_TIME' => true,
            'CURRENT_TIMESTAMP' => true,
            'DATABASE' => true,
            'DEFAULT' => true,
            'DEFERRABLE' => true,
            'DEFERRED' => true,
            'DELETE' => true,
            'DESC' => true,
            'DETACH' => true,
            'DISTINCT' => true,
            'DROP' => true,
            'EACH' => true,
            'ELSE' => true,
            'END' => true,
            'ESCAPE' => true,
            'EXCEPT' => true,
            'EXCLUSIVE' => true,
            'EXISTS' => true,
            'EXPLAIN' => true,
            'FAIL' => true,
            'FOR' => true,
            'FOREIGN' => true,
            'FROM' => true,
            'FULL' => true,
            'GLOB' => true,
            'GROUP' => true,
            'HAVING' => true,
            'IF' => true,
            'IGNORE' => true,
            'IMMEDIATE' => true,
            'IN' => true,
            'INDEX' => true,
            'INDEXED' => true,
            'INITIALLY' => true,
            'INNER' => true,
            'INSERT' => true,
            'INSTEAD' => true,
            'INTERSECT' => true,
            'INTO' => true,
            'IS' => true,
            'ISNULL' => true,
            'JOIN' => true,
            'KEY' => true,
            'LEFT' => true,
            'LIKE' => true,
            'LIMIT' => true,
            'MATCH' => true,
            'NATURAL' => true,
            'NO' => true,
            'NOT' => true,
            'NOTNULL' => true,
            'NULL' => true,
            'OF' => true,
            'OFFSET' => true,
            'ON' => true,
            'OR' => true,
            'ORDER' => true,
            'OUTER' => true,
            'PLAN' => true,
            'PRAGMA' => true,
            'PRIMARY' => true,
            'QUERY' => true,
            'RAISE' => true,
            'RECURSIVE' => true,
            'REFERENCES' => true,
            'REGEXP' => true,
            'REINDEX' => true,
            'RELEASE' => true,
            'RENAME' => true,
            'REPLACE' => true,
            'RESTRICT' => true,
            'RIGHT' => true,
            'ROLLBACK' => true,
            'ROW' => true,
            'SAVEPOINT' => true,
            'SELECT' => true,
            'SET' => true,
            'TABLE' => true,
            'TEMP' => true,
            'TEMPORARY' => true,
            'THEN' => true,
            'TO' => true,
            'TRANSACTION' => true,
            'TRIGGER' => true,
            'UNION' => true,
            'UNIQUE' => true,
            'UPDATE' => true,
            'USING' => true,
            'VACUUM' => true,
            'VALUES' => true,
            'VIEW' => true,
            'VIRTUAL' => true,
            'WHEN' => true,
            'WHERE' => true,
            'WITH' => true,
            'WITHOUT' => true,
        ];

        $string = mb_strtoupper($string, 'UTF-8');

        if (!isset($keywords[$string])) {
            return false;
        }

        $content = $string;

        return true;
    }
}
