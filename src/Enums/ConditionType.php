<?php

namespace Exceedone\Exment\Enums;

use Exceedone\Exment\ConditionItems;

/**
 * Condition type. This enum is parent, child enum is CONDITION->detail.
 * CONDITION
 *      USER
 *      ORGANIZATION
 *      ROLE
 *      FORM
 */
class ConditionType extends EnumBase
{
    const COLUMN = "0";
    const SYSTEM = "1";
    const PARENT_ID = "2";
    const WORKFLOW = "3";
    const CONDITION = "4";
    
    
    public function getConditionItem($custom_table, $target, $target_column_id)
    {
        switch ($this) {
            case static::COLUMN:
                return new ConditionItems\ColumnItem($custom_table, $target);
            case static::SYSTEM:
                return new ConditionItems\SystemItem($custom_table, $target);
            case static::PARENT_ID:
                return new ConditionItems\ParentIdItem($custom_table, $target);
            case static::WORKFLOW:
                return new ConditionItems\WorkflowItem($custom_table, $target);
            case static::CONDITION:
                $detail = ConditionTypeDetail::getEnum($target_column_id);
                if (!isset($detail)) {
                    return null;
                }
                return $detail->getConditionItem($custom_table, $target);
        }
    }

    public static function isTableItem($condition_type)
    {
        return in_array($condition_type, [
            ConditionType::COLUMN,
            ConditionType::SYSTEM,
            ConditionType::PARENT_ID,
        ]);
    }
}
