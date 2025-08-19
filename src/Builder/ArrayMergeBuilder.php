<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Builder;

use Yiisoft\Db\Expression\Function\ArrayMerge;
use Yiisoft\Db\Expression\Function\Builder\MultiOperandFunctionBuilder;
use Yiisoft\Db\Expression\Function\MultiOperandFunction;

use function implode;

/**
 * Builds SQL expressions which merge arrays for {@see ArrayMerge} objects.
 *
 * ```sql
 * (SELECT json_group_array(value) AS value FROM (
 *     SELECT value FROM json_each(operand1)
 *     UNION
 *     SELECT value FROM json_each(operand2)
 * ))
 * ```
 *
 * @extends MultiOperandFunctionBuilder<ArrayMerge>
 */
final class ArrayMergeBuilder extends MultiOperandFunctionBuilder
{
    /**
     * Builds a SQL expression which merges arrays from the given {@see ArrayMerge} object.
     *
     * @param ArrayMerge $expression The expression to build.
     * @param array $params The parameters to bind.
     *
     * @return string The SQL expression.
     */
    protected function buildFromExpression(MultiOperandFunction $expression, array &$params): string
    {
        $builtSelects = [];

        foreach ($expression->getOperands() as $operand) {
            $builtSelects[] = $this->buildSelect($operand, $params);
        }

        return '(SELECT json_group_array(value) AS value FROM (' . implode(' UNION ', $builtSelects) . '))';
    }

    private function buildSelect(mixed $operand, array &$params): string
    {
        $builtOperand = $this->buildOperand($operand, $params);

        return "SELECT value FROM json_each($builtOperand)";
    }
}
