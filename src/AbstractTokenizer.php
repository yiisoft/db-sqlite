<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use SplStack;
use Yiisoft\Db\Exception\InvalidArgumentException;

use function is_array;
use function is_string;
use function mb_strlen;
use function mb_strpos;
use function mb_strtoupper;
use function mb_substr;
use function reset;
use function usort;

/**
 * Splits an SQL query into individual SQL tokens.
 *
 * You can use it to obtain addition information from an SQL code.
 *
 * Usage example:
 *
 * ```php
 * $tokenizer = new SqlTokenizer("SELECT * FROM {{%user}} WHERE [[id]] = 1");
 * $root = $tokenizer->tokenize();
 * $sqlTokens = $root->getChildren();
 * ```
 *
 * Tokens are instances of {@see SqlToken}.
 */
abstract class AbstractTokenizer
{
    /**
     * @var int SQL code string length.
     */
    protected int $length = 0;

    /**
     * @var int SQL code string current offset.
     */
    protected int $offset = 0;

    /**
     * @var SplStack Of active tokens.
     *
     * @psalm-var SplStack<SqlToken>
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private SplStack $tokenStack;

    /**
     * @var array|SqlToken Active token. It's usually a top of the token stack.
     *
     * @psalm-var SqlToken|SqlToken[]
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private array|SqlToken $currentToken;

    /**
     * @var array Cached substrings.
     *
     * @psalm-var string[]
     */
    private array $substrings = [];

    /**
     * @var string Buffer for the current token.
     */
    private string $buffer = '';

    public function __construct(private string $sql)
    {
    }

    /**
     * Tokenizes and returns a code type token.
     *
     * @throws InvalidArgumentException If the SQL code is invalid.
     *
     * @return SqlToken Code type token.
     *
     * @psalm-suppress MixedPropertyTypeCoercion
     */
    public function tokenize(): SqlToken
    {
        $this->length = mb_strlen($this->sql, 'UTF-8');
        $this->offset = 0;
        $this->substrings = [];
        $this->buffer = '';

        $token = (new SqlToken())->type(SqlToken::TYPE_CODE)->content($this->sql);

        $this->tokenStack = new SplStack();
        $this->tokenStack->push($token);

        $token[] = (new SqlToken())->type(SqlToken::TYPE_STATEMENT);

        $this->tokenStack->push($token[0]);
        $this->currentToken = $this->tokenStack->top();
        $length = 0;

        while (!$this->isEof()) {
            if ($this->isWhitespace($length) || $this->isComment($length)) {
                $this->addTokenFromBuffer();
                $this->advance($length);

                continue;
            }

            /** @psalm-suppress ConflictingReferenceConstraint */
            if ($this->tokenizeOperator($length) || $this->tokenizeDelimitedString($length)) {
                $this->advance($length);

                continue;
            }

            $this->buffer .= $this->substring(1);
            $this->advance(1);
        }

        $this->addTokenFromBuffer();

        if (
            $token->getHasChildren() &&
            $token[-1] instanceof SqlToken &&
            !$token[-1]->getHasChildren()
        ) {
            unset($token[-1]);
        }

        return $token;
    }

    /**
     * Returns whether there's a space or blank at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string.
     *
     * @param int $length Length of the matched string.
     *
     * @return bool Whether there's a space or blank at the current offset.
     */
    abstract protected function isWhitespace(int &$length): bool;

    /**
     * Returns whether there's a commentary at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string.
     *
     * @param int $length Length of the matched string.
     *
     * @return bool Whether there's a commentary at the current offset.
     */
    abstract protected function isComment(int &$length): bool;

    /**
     * Returns whether there's an operator at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length  Length of the matched string.
     * @param string|null $content Optional content instead of the matched string.
     *
     * @return bool Whether there's an operator at the current offset.
     */
    abstract protected function isOperator(int &$length, string|null &$content): bool;

    /**
     * Returns whether there's an identifier at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length Length of the matched string.
     * @param string|null $content Optional content instead of the matched string.
     *
     * @return bool Whether there's an identifier at the current offset.
     */
    abstract protected function isIdentifier(int &$length, string|null &$content): bool;

    /**
     * Returns whether there's a string literal at the current offset.
     *
     * If this method returns `true`, it has to set the `$length` parameter to the length of the matched string. It may
     * also set `$content` to a string that will be used as a token content.
     *
     * @param int $length Length of the matched string.
     * @param string|null $content Optional content instead of the matched string.
     *
     * @return bool Whether there's a string literal at the current offset.
     */
    abstract protected function isStringLiteral(int &$length, string|null &$content): bool;

    /**
     * Returns whether the given string is a keyword.
     *
     * The method may set `$content` to a string that will be used as a token content.
     *
     * @param string $string String to match.
     * @param string|null $content Optional content instead of the matched string.
     *
     * @return bool Whether the given string is a keyword.
     */
    abstract protected function isKeyword(string $string, string|null &$content): bool;

    /**
     * Returns whether the longest common prefix equals to the SQL code of the same length at the current offset.
     *
     * @param array $with Strings to test. The method `will` change this parameter to speed up lookups.
     * @param bool $caseSensitive Whether to perform a case-sensitive comparison.
     * @param int $length Length of the matched string.
     * @param string|null $content Matched string.
     *
     * @return bool Whether there is a match.
     *
     * @psalm-param array<array-key, string> $with
     */
    protected function startsWithAnyLongest(
        array $with,
        bool $caseSensitive,
        int &$length,
        string &$content = null
    ): bool {
        if (empty($with)) {
            return false;
        }

        if (!is_array(reset($with))) {
            usort($with, static fn (string $string1, string $string2) => mb_strlen($string2, 'UTF-8') - mb_strlen($string1, 'UTF-8'));

            $map = [];

            foreach ($with as $string) {
                $map[mb_strlen($string, 'UTF-8')][$caseSensitive ? $string : mb_strtoupper($string, 'UTF-8')] = true;
            }

            $with = $map;
        }

        /** @psalm-var array<int, array> $with */
        foreach ($with as $testLength => $testValues) {
            $content = $this->substring($testLength, $caseSensitive);

            if (isset($testValues[$content])) {
                $length = $testLength;
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a string of the given length starting with the specified offset.
     *
     * @param int $length String length to return.
     * @param bool $caseSensitive If it's `false`, the string will be uppercase.
     * @param int|null $offset SQL code offset, defaults to current if `null` is passed.
     *
     * @return string Result string, it may be empty if there's nothing to return.
     */
    protected function substring(int $length, bool $caseSensitive = true, int $offset = null): string
    {
        if ($offset === null) {
            $offset = $this->offset;
        }

        if ($offset + $length > $this->length) {
            return '';
        }

        $cacheKey = $offset . ',' . $length;

        if (!isset($this->substrings[$cacheKey . ',1'])) {
            $this->substrings[$cacheKey . ',1'] = mb_substr($this->sql, $offset, $length, 'UTF-8');
        }

        if (!$caseSensitive && !isset($this->substrings[$cacheKey . ',0'])) {
            $this->substrings[$cacheKey . ',0'] = mb_strtoupper($this->substrings[$cacheKey . ',1'], 'UTF-8');
        }

        return $this->substrings[$cacheKey . ',' . (int) $caseSensitive];
    }

    /**
     * Returns an index after the given string in the SQL code starting with the specified offset.
     *
     * @param string $string String to find.
     * @param int|null $offset SQL code offset, defaults to current if `null` is passed.
     *
     * @return int Index after the given string or end of string index.
     */
    protected function indexAfter(string $string, int $offset = null): int
    {
        if ($offset === null) {
            $offset = $this->offset;
        }

        if ($offset + mb_strlen($string, 'UTF-8') > $this->length) {
            return $this->length;
        }

        $afterIndexOf = mb_strpos($this->sql, $string, $offset, 'UTF-8');

        if ($afterIndexOf === false) {
            $afterIndexOf = $this->length;
        } else {
            $afterIndexOf += mb_strlen($string, 'UTF-8');
        }

        return $afterIndexOf;
    }

    /**
     * Determines whether there is a delimited string at the current offset and adds it to the token children.
     */
    private function tokenizeDelimitedString(int &$length): bool
    {
        $isIdentifier = $this->isIdentifier($length, $content);
        $isStringLiteral = !$isIdentifier && $this->isStringLiteral($length, $content);

        if (!$isIdentifier && !$isStringLiteral) {
            return false;
        }

        $this->addTokenFromBuffer();

        $this->currentToken[] = (new SqlToken())
            ->type($isIdentifier ? SqlToken::TYPE_IDENTIFIER : SqlToken::TYPE_STRING_LITERAL)
            ->content(is_string($content) ? $content : $this->substring($length))
            ->startOffset($this->offset)
            ->endOffset($this->offset + $length);

        return true;
    }

    /**
     * Determines whether there is an operator at the current offset and adds it to the token children.
     */
    private function tokenizeOperator(int &$length): bool
    {
        if (!$this->isOperator($length, $content)) {
            return false;
        }

        $this->addTokenFromBuffer();

        switch ($this->substring($length)) {
            case '(':
                $this->currentToken[] = (new SqlToken())
                    ->type(SqlToken::TYPE_OPERATOR)
                    ->content(is_string($content) ? $content : $this->substring($length))
                    ->startOffset($this->offset)
                    ->endOffset($this->offset + $length);
                $this->currentToken[] = (new SqlToken())->type(SqlToken::TYPE_PARENTHESIS);

                if ($this->currentToken[-1] !== null) {
                    $this->tokenStack->push($this->currentToken[-1]);
                }

                $this->currentToken = $this->tokenStack->top();

                break;

            case ')':
                $this->tokenStack->pop();
                $this->currentToken = $this->tokenStack->top();
                $this->currentToken[] = (new SqlToken())
                    ->type(SqlToken::TYPE_OPERATOR)
                    ->content(')')
                    ->startOffset($this->offset)
                    ->endOffset($this->offset + $length);

                break;
            case ';':
                if ($this->currentToken instanceof SqlToken && !$this->currentToken->getHasChildren()) {
                    break;
                }

                $this->currentToken[] = (new SqlToken())
                    ->type(SqlToken::TYPE_OPERATOR)
                    ->content(is_string($content) ? $content : $this->substring($length))
                    ->startOffset($this->offset)
                    ->endOffset($this->offset + $length);
                $this->tokenStack->pop();
                $this->currentToken = $this->tokenStack->top();
                $this->currentToken[] = (new SqlToken())->type(SqlToken::TYPE_STATEMENT);

                if ($this->currentToken[-1] instanceof SqlToken) {
                    $this->tokenStack->push($this->currentToken[-1]);
                }

                $this->currentToken = $this->tokenStack->top();

                break;
            default:
                $this->currentToken[] = (new SqlToken())
                    ->type(SqlToken::TYPE_OPERATOR)
                    ->content(is_string($content) ? $content : $this->substring($length))
                    ->startOffset($this->offset)
                    ->endOffset($this->offset + $length);

                break;
        }

        return true;
    }

    /**
     * Determines a type of text in the buffer, tokenizes it and adds it to the token children.
     */
    private function addTokenFromBuffer(): void
    {
        if ($this->buffer === '') {
            return;
        }

        $isKeyword = $this->isKeyword($this->buffer, $content);

        $this->currentToken[] = (new SqlToken())
            ->type($isKeyword ? SqlToken::TYPE_KEYWORD : SqlToken::TYPE_TOKEN)
            ->content(is_string($content) ? $content : $this->buffer)
            ->startOffset($this->offset - mb_strlen($this->buffer, 'UTF-8'))
            ->endOffset($this->offset);

        $this->buffer = '';
    }

    /**
     * Adds the specified length to the current offset.
     *
     * @throws InvalidArgumentException If the length is less than or equal to 0.
     */
    private function advance(int $length): void
    {
        if ($length <= 0) {
            throw new InvalidArgumentException('Length must be greater than 0.');
        }

        $this->offset += $length;
        $this->substrings = [];
    }

    /**
     * Returns whether the SQL code is completely traversed.
     */
    private function isEof(): bool
    {
        return $this->offset >= $this->length;
    }
}
