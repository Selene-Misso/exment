<?php

namespace Exceedone\Exment\ColumnItems;

use Exceedone\Exment\Enums\SummaryCondition;
use Exceedone\Exment\Enums\GroupCondition;
use Exceedone\Exment\Model\CustomColumn;

/**
 *
 * @property CustomColumn $custom_column
 */
trait SummaryItemTrait
{
    //for summary  --------------------------------------------------

    /**
     * Get summary condion name.
     * SUM, COUNT, MIN, MAX
     *
     * @return string|null
     */
    protected function getSummaryConditionName()
    {
        $summary_option = array_get($this->options, 'summary_condition');
        $summary_condition = is_null($summary_option) ? null : SummaryCondition::getEnum($summary_option)->lowerKey();
        return $summary_condition;
    }

    /**
     * get sqlname for summary
     */
    protected function getSummarySqlName()
    {
        $options = $this->getSummaryParams();
        $value_column = $options['value_column'];
        $group_condition = $options['group_condition'];

        $summary_condition = $this->getSummaryConditionName();
        
        if (isset($summary_condition)) {
            // get cast
            $castColumn = $this->getCastColumn($value_column, false, false);
            $raw = "$summary_condition($castColumn) AS ".$this->sqlAsName();
        } elseif (isset($group_condition)) {
            $raw = \DB::getQueryGrammar()->getDateFormatString($group_condition, $value_column, false) . " AS ".$this->sqlAsName();
        } else {
            $raw = "$value_column AS ".$this->sqlAsName();
        }

        return \DB::raw($raw);
    }
    
    /**
     * get sqlname for summary
     */
    protected function getGroupBySqlName()
    {
        $options = $this->getSummaryParams();
        $value_column = $options['value_column'];
        $group_condition = $options['group_condition'];
        $is_child = $options['is_child'];
        
        // get column_name. toggle whether is child or not
        if ($is_child) {
            $column_name = $this->sqlAsName();
        } else {
            $column_name = $value_column;
        }

        if (isset($group_condition)) {
            $raw = \DB::getQueryGrammar()->getDateFormatString($group_condition, $column_name, true);
        } else {
            $raw = $column_name;
        }

        return \DB::raw($raw);
    }

    protected function getSummaryParams()
    {
        $db_table_name = getDBTableName($this->custom_column->custom_table_cache);
        $column_name = $this->custom_column->column_name;

        $group_condition = array_get($this->options, 'group_condition');
        $group_condition = isset($group_condition) ? GroupCondition::getEnum($group_condition) : null;
        
        $is_child = array_get($this->options, 'is_child');

        // get value_column
        $json_column = \DB::getQueryGrammar()->wrapJsonUnquote("$db_table_name.value->$column_name");
        $value_column = ($this->custom_column->index_enabled) ? $this->index() : $json_column;
        
        return [
            'db_table_name' => $db_table_name,
            'column_name' => $column_name,
            'group_condition' => $group_condition,
            'json_column' => $json_column,
            'value_column' => $value_column,
            'is_child' => $is_child,
        ];
    }
    
    public function sqlAsName()
    {
        return "column_".array_get($this->options, 'summary_index');
    }

    public function getGroupName()
    {
        $db_table_name = getDBTableName($this->custom_column->custom_table);
        $column_name = $this->custom_column->column_name;
        
        $summary_condition = $this->getSummaryConditionName();
        $alter_name = $this->sqlAsName();
        $raw = "$summary_condition($alter_name) AS $alter_name";

        return \DB::raw($raw);
    }
    
    /**
     * Get API column name
     *
     * @return string
     */
    protected function _apiName()
    {
        return array_get($this->options, 'view_column_suuid');
    }
}
