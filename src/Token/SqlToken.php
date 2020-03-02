<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Token;

/**
 * SqlToken represents SQL tokens produced by {@see SqlTokenizer} or its child classes.
 *
 * @property SqlToken[] $children Child tokens.
 * @property bool $hasChildren Whether the token has children. This property is read-only.
 * @property bool $isCollection Whether the token represents a collection of tokens. This property is
 * read-only.
 * @property string $sql SQL code. This property is read-only.
 */
class SqlToken implements \ArrayAccess
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
    private ?string $content = null;
    private ?int $startOffset = null;
    private ?int $endOffset = null;
    private ?SqlToken $parent = null;
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
     * This method is required by the SPL {@see \ArrayAccess} interface. It is implicitly called when you use something
     * like `isset($token[$offset])`.
     *
     * @param int $offset child token offset.
     *
     * @return bool whether the token exists.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->children[$this->calculateOffset($offset)]);
    }

    /**
     * Returns a child token at the specified offset.
     *
     * This method is required by the SPL {@see \ArrayAccess} interface. It is implicitly called when you use something
     * like `$child = $token[$offset];`.
     *
     * @param int $offset child token offset.
     *
     * @return SqlToken|null the child token at the specified offset, `null` if there's no token.
     */
    public function offsetGet($offset): ?SqlToken
    {
        $offset = $this->calculateOffset($offset);

        return $this->children[$offset] ?? null;
    }

    /**
     * Adds a child token to the token.
     *
     * This method is required by the SPL {@see \ArrayAccess} interface. It is implicitly called when you use something
     * like `$token[$offset] = $child;`.
     *
     * @param int|null $offset child token offset.
     * @param SqlToken $token  token to be added.
     */
    public function offsetSet($offset, $token): void
    {
        $token->parent = $this;

        if ($offset === null) {
            $this->children[] = $token;
        } else {
            $this->children[$this->calculateOffset($offset)] = $token;
        }

        $this->updateCollectionOffsets();
    }

    /**
     * Removes a child token at the specified offset.
     *
     * This method is required by the SPL {@see \ArrayAccess} interface. It is implicitly called when you use something
     * like `unset($token[$offset])`.
     *
     * @param int $offset child token offset.
     */
    public function offsetUnset($offset): void
    {
        $offset = $this->calculateOffset($offset);

        if (isset($this->children[$offset])) {
            \array_splice($this->children, $offset, 1);
        }

        $this->updateCollectionOffsets();
    }

    /**
     * Returns child tokens.
     *
     * @return SqlToken[] child tokens.
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Sets a list of child tokens.
     *
     * @param SqlToken[] $children child tokens.
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
     * @return bool whether the token represents a collection of tokens.
     */
    public function getIsCollection(): bool
    {
        return \in_array($this->type, [
            self::TYPE_CODE,
            self::TYPE_STATEMENT,
            self::TYPE_PARENTHESIS,
        ], true);
    }

    /**
     * Returns whether the token represents a collection of tokens and has non-zero number of children.
     *
     * @return bool whether the token has children.
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
        $code = $this;
        while ($code->parent !== null) {
            $code = $code->parent;
        }

        return mb_substr($code->content, $this->startOffset, $this->endOffset - $this->startOffset, 'UTF-8');
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
     * @param SqlToken $patternToken tokenized SQL code to match against. In addition to normal SQL, the `any` keyword
     * is supported which will match any number of keywords, identifiers, whitespaces.
     * @param int $offset token children offset to start lookup with.
     * @param int|null $firstMatchIndex token children offset where a successful match begins.
     * @param int|null $lastMatchIndex  token children offset where a successful match ends.
     *
     * @return bool whether this token matches the pattern SQL code.
     */
    public function matches(
        self $patternToken,
        int $offset = 0,
        ?int &$firstMatchIndex = null,
        ?int &$lastMatchIndex = null
    ): bool {
        if (!$patternToken->getHasChildren()) {
            return false;
        }

        $patternToken = $patternToken[0];

        return $this->tokensMatch($patternToken, $this, $offset, $firstMatchIndex, $lastMatchIndex);
    }

    /**
     * Tests the given token to match the specified pattern token.
     *
     * @param SqlToken $patternToken
     * @param SqlToken $token
     * @param int $offset
     * @param int|null $firstMatchIndex
     * @param int|null $lastMatchIndex
     *
     * @return bool
     */
    private function tokensMatch(
        self $patternToken,
        self $token,
        int $offset = 0,
        ?int &$firstMatchIndex = null,
        ?int &$lastMatchIndex = null
    ): bool {
        if ($patternToken->getIsCollection() !== $token->getIsCollection() || (!$patternToken->getIsCollection() && $patternToken->content !== $token->content)) {
            return false;
        }

        if ($patternToken->children === $token->children) {
            $firstMatchIndex = $lastMatchIndex = $offset;

            return true;
        }

        $firstMatchIndex = $lastMatchIndex = null;
        $wildcard = false;

        for ($index = 0, $count = count($patternToken->children); $index < $count; $index++) {
            /**
             *  Here we iterate token by token with an exception of "any" that toggles an iteration until we matched
             *  with a next pattern token or EOF.
             */
            if ($patternToken[$index]->content === 'any') {
                $wildcard = true;
                continue;
            }

            for ($limit = $wildcard ? count($token->children) : $offset + 1; $offset < $limit; $offset++) {
                if (!$wildcard && !isset($token[$offset])) {
                    break;
                }

                if (!$this->tokensMatch($patternToken[$index], $token[$offset])) {
                    continue;
                }

                if ($firstMatchIndex === null) {
                    $firstMatchIndex = $offset;
                    $lastMatchIndex = $offset;
                } else {
                    $lastMatchIndex = $offset;
                }

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
     *
     * @param int $offset
     *
     * @return int
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

        if ($this->parent !== null) {
            $this->parent->updateCollectionOffsets();
        }
    }

    /**
     * Set token type. It has to be one of the following constants:
     *
     * - {@see TYPE_CODE}
     * - {@see TYPE_STATEMENT}
     * - {@see TYPE_TOKEN}
     * - {@see TYPE_PARENTHESIS}
     * - {@see TYPE_KEYWORD}
     * - {@see TYPE_OPERATOR}
     * - {@see TYPE_IDENTIFIER}
     * - {@see TYPE_STRING_LITERAL}
     *
     * @param int $value token type. It has to be one of the following constants:
     */
    public function setType(int $value): void
    {
        $this->type = $value;
    }

    /**
     * Set token content.
     *
     * @param string|null $content token content.
     */
    public function setContent(?string $value): void
    {
        $this->content = $value;
    }

    /**
     * Set original SQL token start position.
     *
     * @param int $value original SQL token start position.
     */
    public function setStartOffset(int $value): void
    {
        $this->startOffset = $value;
    }

    /**
     * Set original SQL token end position.
     *
     * @param int $value original SQL token end position.
     */
    public function setEndOffset(int $value): void
    {
        $this->endOffset = $value;
    }

    /**
     * Set parent token.
     *
     * @param SqlToken $value parent token.
     */
    public function setParent(SqlToken $value): void
    {
        $this->parent = $value;
    }
}
