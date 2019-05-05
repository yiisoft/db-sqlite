<?php
/**
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Db\Sqlite\Conditions;

/**
 * {@inheritdoc}
 */
class LikeConditionBuilder extends \Yiisoft\Db\Conditions\LikeConditionBuilder
{
    /**
     * {@inheritdoc}
     */
    protected $escapeCharacter = '\\';
}
