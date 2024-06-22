<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite;

use ArrayAccess;
use Stringable;

use function array_splice;
use function count;
use function end;
use function in_array;
use function mb_substr;
use function reset;

/**
 * Represents SQL tokens produced by {@see SqlTokenizer} or its child classes.
 *
 * @template-implements ArrayAccess<int, SqlToken>
 */
final class SqlToken implements ArrayAccess, Stringable
{
    public const TYPE_CODE = 0;
    public const TYPE_STATEMENT = 1;
    public const TYPE_TOKEN = 2;
    public const TYPE_PARENTHESIS = 3;
    public const TYPE_KEYWORD = 4;
    public const TYPE_OPERATOR = 5;
    public const TYPE_IDENTIFIER = 6;
    public const TYPE_STRING_LITERAL = 7;
    private int $type = self::TYPE_TOKEN;
    private string|null $content = null;
    private int|null $startOffset = null;
    private int|null $endOffset = null;
    private SqlToken|null $parent = null;
    /**
     * @var array list of child tokens.
     *
     * @psalm-var array<int, SqlToken>
     */
    private array $children = [];

    /**
     * Returns the SQL code representing the token.
     *
     * @return string SQL code.
     */
    public function __toString(): string
    {
        return $this->getSql();
    }

    /**
     * Returns whether there is a child token at the specified offset.
     *
     * This method is required by the SPL {@see ArrayAccess} interface.
     *
     * It's implicitly called when you use something like `isset($token[$offset])`.
     *
     * @param int $offset The child token offset.
     *
     * @return bool Whether the token exists.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->children[$this->calculateOffset($offset)]);
    }

    /**
     * Returns a child token at the specified offset.
     *
     * This method is required by the SPL {@see ArrayAccess} interface.
     *
     * It's implicitly called when you use something like `$child = $token[$offset];`.
     *
     * @param int $offset The child token offset.
     *
     * @return SqlToken|null The child token at the specified offset, `null` if there's no token.
     */
    public function offsetGet($offset): self|null
    {
        $offset = $this->calculateOffset($offset);

        return $this->children[$offset] ?? null;
    }

    /**
     * Adds a child token to the token.
     *
     * This method is required by the SPL {@see ArrayAccess} interface.
     *
     * It's implicitly called when you use something like `$token[$offset] = $child;`.
     *
     * @param mixed $offset The child token offset.
     * @param mixed $value Token to add.
     *
     * @psalm-suppress MixedPropertyTypeCoercion
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value instanceof self) {
            $value->parent = $this;
        }

        if ($offset === null) {
            $this->children[] = $value;
        } else {
            $this->children[$this->calculateOffset((int) $offset)] = $value;
        }

        $this->updateCollectionOffsets();
    }

    /**
     * Removes a child token at the specified offset.
     *
     * This method is required by the SPL {@see ArrayAccess} interface.
     *
     * It's implicitly called when you use something like `unset($token[$offset])`.
     *
     * @param int $offset Child token offset.
     */
    public function offsetUnset($offset): void
    {
        $offset = $this->calculateOffset($offset);

        if (isset($this->children[$offset])) {
            array_splice($this->children, $offset, 1);
        }

        $this->updateCollectionOffsets();
    }

    /**
     * Returns child tokens.
     *
     * @return SqlToken[] Child tokens.
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Sets a list of child tokens.
     *
     * @param SqlToken[] $children Child tokens.
     */
    public function setChildren(array $children): void
    {
        $this->children = [];

        foreach ($children as $child) {
            $child->parent = $this;
            $this->children[] = $child;
        }

        $this->updateCollectionOffsets();
    }

    /**
     * Returns whether the token represents a collection of tokens.
     *
     * @return bool Whether the token represents a collection of tokens.
     */
    public function getIsCollection(): bool
    {
        return in_array($this->type, [self::TYPE_CODE, self::TYPE_STATEMENT, self::TYPE_PARENTHESIS], true);
    }

    /**
     * Returns whether the token represents a collection of tokens and has a non-zero number of children.
     *
     * @return bool Whether the token has children.
     */
    public function getHasChildren(): bool
    {
        return $this->getIsCollection() && !empty($this->children);
    }

    /**
     * Returns the SQL code representing the token.
     *
     * @return string SQL code.
     */
    public function getSql(): string
    {
        $sql = '';
        $code = $this;

        while ($code->parent !== null) {
            $code = $code->parent;
        }

        if ($code->content !== null) {
            $sql = mb_substr(
                $code->content,
                (int) $this->startOffset,
                (int) $this->endOffset - (int) $this->startOffset,
                'UTF-8',
            );
        }

        return $sql;
    }

    /**
     * Returns whether this token (including its children) matches the specified "pattern" SQL code.
     *
     * Usage Example:
     *
     * ```php
     * $patternToken = (new \Yiisoft\Db\Sqlite\SqlTokenizer('SELECT any FROM any'))->tokenize();
     * if ($sqlToken->matches($patternToken, 0, $firstMatchIndex, $lastMatchIndex)) {
     *     // ...
     * }
     * ```
     *
     * @param SqlToken $patternToken Tokenized SQL codes to match.
     * In addition to regular SQL, the `any` keyword is supported which will match any number of keywords, identifiers,
     * whitespaces.
     * @param int $offset Token children offset to start lookup with.
     * @param int|null $firstMatchIndex Token children offset where a successful match begins.
     * @param int|null $lastMatchIndex Token children offset where a successful match ends.
     *
     * @return bool Whether this token matches the pattern SQL code.
     */
    public function matches(
        self $patternToken,
        int $offset = 0,
        int &$firstMatchIndex = null,
        int &$lastMatchIndex = null
    ): bool {
        $result = false;

        if ($patternToken->getHasChildren() && ($patternToken[0] instanceof self)) {
            $result = $this->tokensMatch($patternToken[0], $this, $offset, $firstMatchIndex, $lastMatchIndex);
        }

        return $result;
    }

    /**
     * Tests the given token to match the specified pattern token.
     */
    private function tokensMatch(
        self $patternToken,
        self $token,
        int $offset = 0,
        int &$firstMatchIndex = null,
        int &$lastMatchIndex = null
    ): bool {
        if (
            $patternToken->getIsCollection() !== $token->getIsCollection() ||
            (!$patternToken->getIsCollection() && $patternToken->content !== $token->content)
        ) {
            return false;
        }

        if ($patternToken->children === $token->children) {
            $firstMatchIndex = $lastMatchIndex = $offset;

            return true;
        }

        $firstMatchIndex = $lastMatchIndex = null;
        $wildcard = false;

        for ($index = 0, $count = count($patternToken->children); $index < $count; $index++) {
            /*
             Iterate token by token with an exception to "any" that toggles an iteration until matched
             with a next pattern token or EOF.
             */
            if ($patternToken[$index] instanceof self && $patternToken[$index]->content === 'any') {
                $wildcard = true;
                continue;
            }

            for ($limit = $wildcard ? count($token->children) : $offset + 1; $offset < $limit; $offset++) {
                if (!$wildcard && !isset($token[$offset])) {
                    break;
                }

                if (
                    $patternToken[$index] instanceof self &&
                    $token[$offset] instanceof self  &&
                    !$this->tokensMatch($patternToken[$index], $token[$offset])
                ) {
                    continue;
                }

                if ($firstMatchIndex === null) {
                    $firstMatchIndex = $offset;
                }

                $lastMatchIndex = $offset;
                $wildcard = false;
                $offset++;

                continue 2;
            }

            return false;
        }

        return true;
    }

    /**
     * Returns an absolute offset in the children array.
     */
    private function calculateOffset(int $offset): int
    {
        if ($offset >= 0) {
            return $offset;
        }

        return count($this->children) + $offset;
    }

    /**
     * Updates token SQL code start and end offsets based on its children.
     */
    private function updateCollectionOffsets(): void
    {
        if (!empty($this->children)) {
            $this->startOffset = reset($this->children)->startOffset;
            $this->endOffset = end($this->children)->endOffset;
        }

        $this->parent?->updateCollectionOffsets();
    }

    /**
     * Set token type.
     *
     * @param int $value Token type.
     * It has to be one of the following constants:
     *
     * - {@see TYPE_CODE}
     * - {@see TYPE_STATEMENT}
     * - {@see TYPE_TOKEN}
     * - {@see TYPE_PARENTHESIS}
     * - {@see TYPE_KEYWORD}
     * - {@see TYPE_OPERATOR}
     * - {@see TYPE_IDENTIFIER}
     * - {@see TYPE_STRING_LITERAL}
     */
    public function type(int $value): self
    {
        $this->type = $value;

        return $this;
    }

    /**
     * Set token content.
     */
    public function content(string|null $value): self
    {
        $this->content = $value;

        return $this;
    }

    /**
     * Set original SQL token start position.
     *
     * @param int $value Original SQL token start position.
     */
    public function startOffset(int $value): self
    {
        $this->startOffset = $value;

        return $this;
    }

    /**
     * Set original SQL token end position.
     *
     * @param int $value Original SQL token end position.
     */
    public function endOffset(int $value): self
    {
        $this->endOffset = $value;

        return $this;
    }

    /**
     * Set parent token.
     *
     * @param SqlToken $value The parent token.
     */
    public function parent(self $value): self
    {
        $this->parent = $value;

        return $this;
    }

    /**
     * @return string|null The token content.
     */
    public function getContent(): string|null
    {
        return $this->content;
    }

    /**
     * @return int The type of the token.
     */
    public function getType(): int
    {
        return $this->type;
    }
}
