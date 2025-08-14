<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Expression\Function\Builder\MultiOperandFunctionBuilder;
use Yiisoft\Db\Expression\Function\Least;
use Yiisoft\Db\Expression\Function\MultiOperandFunction;

use function implode;

/**
 * Builds SQL MIN() function expressions for {@see Least} objects.
 */
final class LeastBuilder extends MultiOperandFunctionBuilder
{
    /**
     * Builds a SQL MIN() function expression from the given {@see Least} object.
     *
     * @param Least $expression The expression to build.
     * @param array $params The parameters to bind.
     *
     * @return string The SQL MIN() function expression.
     */
    protected function buildFromExpression(MultiOperandFunction $expression, array &$params): string
    {
        $builtOperands = [];

        foreach ($expression->getOperands() as $operand) {
            $builtOperands[] = $this->buildOperand($operand, $params);
        }

        return 'MIN(' . implode(', ', $builtOperands) . ')';
    }
}
