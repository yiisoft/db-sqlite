<?php

declare(strict_types=1);

namespace Yiisoft\Db\Sqlite\Schema;

use Yiisoft\Db\Schemas\ColumnSchemaBuilder as AbstractColumnSchemaBuilder;

/**
 * ColumnSchemaBuilder is the schema builder for Sqlite databases.
 */
class ColumnSchemaBuilder extends AbstractColumnSchemaBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function buildUnsignedString(): string
    {
        return $this->isUnsigned ? ' UNSIGNED' : '';
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        switch ($this->getTypeCategory()) {
            case self::CATEGORY_PK:
                $format = '{type}{check}{append}';
                break;
            case self::CATEGORY_NUMERIC:
                $format = '{type}{length}{unsigned}{notnull}{unique}{check}{default}{append}';
                break;
            default:
                $format = '{type}{length}{notnull}{unique}{check}{default}{append}';
        }

        return $this->buildCompleteString($format);
    }
}
