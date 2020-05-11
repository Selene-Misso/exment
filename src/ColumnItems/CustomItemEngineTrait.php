<?php

namespace Exceedone\Exment\ColumnItems;

use Encore\Admin\Form;
use Encore\Admin\Form\Field;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Grid\Filter\Where;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Exceedone\Exment\ColumnItems\CustomColumns\AutoNumber;
use Exceedone\Exment\ColumnItems\CustomItem;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ConditionType;
use Exceedone\Exment\Enums\CurrencySymbol;
use Exceedone\Exment\Enums\FilterSearchType;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Enums\FormBlockType;
use Exceedone\Exment\Enums\FormColumnType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ViewKindType;
use Exceedone\Exment\Form\Field as ExmentField;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Grid\Filter as ExmentFilter;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomColumnMulti;
use Exceedone\Exment\Model\CustomForm;
use Exceedone\Exment\Model\CustomFormColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\CustomViewColumn;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Traits\ColumnOptionQueryTrait;
use Exceedone\Exment\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


/**
 * For custom item engine(System) logic.
 */
trait CustomItemEngineTrait
{
    /**
     * Available fields.
     *
     * @var array
     */
    public static $availableFields = [];

    /**
     * Register custom field.
     *
     * @param string $abstract
     * @param string $class
     *
     * @return void
     */
    public static function extend($abstract, $class)
    {
        static::$availableFields[$abstract] = $class;
    }
    
    /**
     * Get custom column's option
     *
     * @param [type] $name
     * @param [type] $default
     * @return void
     */
    public function getColumnOption($name, $default = null)
    {
        if(!isset($this->custom_column)){
            return $default;
        }

        return $this->custom_column->getOption($name, $default) ?? $default;
    }

    /**
     * get grid style
     */
    public function gridStyle()
    {
        return $this->getStyleString([
            'min-width' => $this->custom_column->getOption('min_width', config('exment.grid_min_width', 100)) . 'px',
            'max-width' => $this->custom_column->getOption('max_width', config('exment.grid_max_width', 300)) . 'px',
        ]);
    }

    /**
     * sortable for grid
     */
    public function sortable()
    {
        return $this->indexEnabled() && !array_key_value_exists('view_pivot_column', $this->options);
    }

    /**
     * set item label
     */
    public function setLabel($label)
    {
        return $this->label = $label;
    }

    public function setCustomValue($custom_value)
    {
        $this->custom_value = $custom_value;
        $this->value = $this->getTargetValue($custom_value);
        if (isset($custom_value)) {
            $this->id = array_get($custom_value, 'id');
        }

        $this->prepare();
        
        return $this;
    }

    public function getCustomTable()
    {
        return $this->custom_table;
    }

    protected function getTargetValue($custom_value)
    {
        // if options has "summary" (for summary view)
        if (boolval(array_get($this->options, 'summary'))) {
            return array_get($custom_value, $this->sqlAsName());
        }
        // if options has "summary_child" (for not only summary view, but also default view)
        if (isset($custom_value) && boolval(array_get($this->options, 'summary_child'))) {
            return $custom_value->getSum($this->custom_column);
        }

        // if options has "view_pivot_column", get select_table's custom_value first
        if (isset($custom_value) && array_key_value_exists('view_pivot_column', $this->options)) {
            $view_pivot_column = $this->options['view_pivot_column'];
            if ($view_pivot_column == SystemColumn::PARENT_ID) {
                $custom_value = $this->custom_table->getValueModel($custom_value->parent_id);
            } else {
                $pivot_custom_column = CustomColumn::getEloquent($this->options['view_pivot_column']);
                $pivot_id =  $custom_value->pureValue($pivot_custom_column);
                $custom_value = $this->custom_table->getValueModel($pivot_id);
            }
        }

        return isset($custom_value) ? $custom_value->pureValue($this->custom_column) : null;
    }
    
    public function getFilterField($value_type = null)
    {
        if (get_class($this) == AutoNumber::class) {
            $field = $this->getCustomField(Field\Text::class);
            return $field->default('');
        } else {
            switch ($value_type) {
                case FilterType::DAY:
                    $classname = Field\Date::class;
                    break;
                case FilterType::NUMBER:
                    $classname = Field\Number::class;
                    break;
                case FilterType::SELECT:
                    $classname = Field\Select::class;
                    break;
                default:
                    $classname = $this->getFilterFieldClass();
                    break;
            }
        }

        // set disable_number_format
        $this->custom_column->setOption('number_format', false);
        $this->options['disable_number_format'] = true;

        return $this->getCustomField($classname);
    }
    
    protected function getFilterFieldClass()
    {
        return $this->getAdminFieldClass();
    }

    public function getAdminField($form_column = null, $column_name_prefix = null)
    {
        $form_column_options = $form_column->options ?? null;

        // if hidden setting, add hidden field
        if (boolval(array_get($form_column_options, 'hidden'))) {
            $classname = Field\Hidden::class;
        } elseif ($this->initonly() && !is_null($this->value())) {
            $classname = ExmentField\Display::class;
        } else {
            // get field
            $classname = $this->getAdminFieldClass();
        }

        return $this->getCustomField($classname, $form_column_options, $column_name_prefix);
    }

    /**
     * Custom form field.
     *
     * @param [type] $classname
     * @param [type] $form_column_options
     * @param [type] $column_name_prefix
     * @return void
     */
    protected function getCustomField($classname, $form_column_options = null, $column_name_prefix = null)
    {
        $options = $this->custom_column->options;
        // form column name. join $column_name_prefix and $column_name
        $form_column_name = $column_name_prefix.$this->name();
        
        $field = new $classname($form_column_name, [$this->label()]);
        if ($this->isSetAdminOptions($form_column_options)) {
            $this->setAdminOptions($field, $form_column_options);
        }

        if (!boolval(array_get($form_column_options, 'hidden')) && $this->initonly() && !is_null($this->value())) {
            $field->displayText($this->html());
        }

        ///////// get common options
        if (array_key_value_exists('placeholder', $options)) {
            $field->placeholder(array_get($options, 'placeholder'));
        }

        // default
        if (!is_null($this->defaultForm())) {
            $field->default($this->defaultForm());
        }

        // number_format
        if (boolval(array_get($options, 'number_format'))) {
            $field->attribute(['number_format' => true]);
        }

        // // readonly
        if (boolval(array_get($form_column_options, 'view_only'))) {
            $field->readonly();
        }

        // required
        if ((boolval(array_get($options, 'required')) || boolval(array_get($form_column_options, 'required')))
            && $this->required) {
            $field->required();
            $field->rules('required');
        } else {
            $field->rules('nullable');
        }

        // suggest input
        if (boolval(array_get($options, 'suggest_input'))) {
            $url = admin_urls('webapi/data', $this->custom_table->table_name, 'column', $this->name());
            $field->attribute(['suggest_url' => $url]);
        }

        // set validates
        $validate_options = [];
        $validates = $this->getColumnValidates($validate_options, $form_column_options);
        // set validates
        if (count($validates)) {
            $field->rules($validates);
        }

        // set help string using result_options
        $help = null;
        if (array_key_value_exists('help', $options)) {
            $help = array_get($options, 'help');
        }
        $help_regexes = array_get($validate_options, 'help_regexes');
        
        // if initonly is true and has value, not showing help
        if ($this->initonly() && !is_null($this->value())) {
            $help = null;
        }
        // if initonly is true and now, showing help and cannot edit help
        elseif ($this->initonly() && is_null($this->value())) {
            $help .= exmtrans('common.help.init_flg');
            if (isset($help_regexes)) {
                $help .= sprintf(exmtrans('common.help.input_available_characters'), implode(exmtrans('common.separate_word'), $help_regexes));
            }
        }
        // if initonly is false, showing help
        else {
            if (isset($help_regexes)) {
                $help .= sprintf(exmtrans('common.help.input_available_characters'), implode(exmtrans('common.separate_word'), $help_regexes));
            }
        }

        if (isset($help)) {
            $field->help(esc_html($help));
        }

        $field->attribute(['data-column_type' => $this->custom_column->column_type]);

        return $field;
    }

    /**
     * set admin filter
     */
    public function setAdminFilter(&$filter)
    {
        $classname = $this->getAdminFilterClass();

        // if where query, call Cloquire
        if ($classname == Where::class) {
            $item = $this;
            $filteritem = new $classname(function ($query) use ($item) {
                $item->getAdminFilterWhereQuery($query, $this->input);
            }, $this->label(), $this->index());
        } else {
            $filteritem = new $classname($this->index(), $this->label());
        }

        $filteritem->showNullCheck();

        // first, set $filter->use
        $filter->use($filteritem);

        // next, set admin filter options
        $this->setAdminFilterOptions($filteritem);
    }

    public static function getItem(...$args)
    {
        list($custom_column, $custom_value, $view_column_target) = $args + [null, null, null];

        $column_type = $custom_column->column_type;

        if ($className = static::findItemClass($column_type)) {
            return new $className($custom_column, $custom_value, $view_column_target);
        }
        
        admin_error('Error', "Field type [$column_type] does not exist.");

        return null;
    }
    
    /**
     * Find item class.
     *
     * @param string $column_type
     *
     * @return bool|mixed
     */
    public static function findItemClass($column_type)
    {
        if(!isset($column_type)){
            return false;
        }
        
        $class = array_get(static::$availableFields, $column_type);

        if (class_exists($class)) {
            return $class;
        }

        return false;
    }

    /**
     * Get column validate array.
     * @param array $result_options
     * @param mixed $form_column_options
     * @return array
     */
    protected function getColumnValidates(&$result_options, $form_column_options)
    {
        $options = array_get($this->custom_column, 'options');

        $validates = [];
        // setting options --------------------------------------------------
        // unique
        if (boolval(array_get($options, 'unique')) && !boolval(array_get($options, 'multiple_enabled'))) {
            // add unique field
            $unique_table_name = getDBTableName($this->custom_table); // database table name
            $unique_column_name = "value->".array_get($this->custom_column, 'column_name'); // column name
            
            $uniqueRules = [$unique_table_name, $unique_column_name];
            // create rules.if isset id, add
            $uniqueRules[] = $this->id ?? '';
            $uniqueRules[] = 'id';
            // and ignore data deleted_at is NULL
            $uniqueRules[] = 'deleted_at';
            $uniqueRules[] = 'NULL';
            $rules = "unique:".implode(",", $uniqueRules);
            // add rules
            $validates[] = $rules;
        }

        // init_flg(for validation)
        if ($this->initonly()) {
            $validates[] = new Validator\InitOnlyRule($this->custom_column, $this->custom_value);
        }

        // // regex rules
        $help_regexes = [];
        if (boolval(config('exment.expart_mode', false)) && array_key_value_exists('regex_validate', $options)) {
            $regex_validate = array_get($options, 'regex_validate');
            $validates[] = 'regex:/'.$regex_validate.'/u';
        } elseif (array_key_value_exists('available_characters', $options)) {
            $difinitions = CustomColumn::getAvailableCharacters();

            $available_characters = stringToArray(array_get($options, 'available_characters') ?? []);
            $regexes = [];
            // add regexes using loop
            foreach ($available_characters as $available_character) {
                // get available_character define
                $define = collect($difinitions)->first(function ($d) use ($available_character) {
                    return array_get($d, 'key') == $available_character;
                });
                if (!isset($define)) {
                    continue;
                }

                $regexes[] = array_get($define, 'regex');
                $help_regexes[] = array_get($define, 'label');
            }
            if (count($regexes) > 0) {
                $validates[] = 'regex:/^['.implode("", $regexes).']*$/u';
            }
        }
        
        // set help_regexes to result_options
        if (count($help_regexes) > 0) {
            $result_options['help_regexes'] = $help_regexes;
        }

        // set column's validates
        $this->setValidates($validates, $form_column_options);

        return $validates;
    }

    protected function initonly()
    {
        $initOnly = boolval(array_get($this->custom_column->options, 'init_only'));
        $required = boolval(array_get($this->custom_column->options, 'required'));

        // if init only, required, and set value, set $this->required is false
        if ($initOnly && !is_null($this->value())) {
            $this->required = false;
        }
        return $initOnly;
    }

    protected function isSetAdminOptions($form_column_options)
    {
        if (boolval(array_get($form_column_options, 'hidden'))) {
            return false;
        } elseif ($this->initonly() && !is_null($this->value())) {
            return false;
        }

        return true;
    }
    
    public static function getColumnTypesSelectTable(){
        return static::getColumnTypes('isSelectTable');
    }

    public static function getColumnTypesUserOrganization(){
        return static::getColumnTypes('isUserOrganization');
    }

    public static function getColumnTypesCalc(){
        return static::getColumnTypes('isCalc');
    }

    public static function getColumnTypesDate(){
        return static::getColumnTypes('isDate');
    }

    public static function getColumnTypesDatetime(){
        return static::getColumnTypes('isDatetime');
    }

    public static function getColumnTypesEmail(){
        return static::getColumnTypes('isEmail');
    }

    public static function getColumnTypesText(){
        return static::getColumnTypes('isText');
    }

    public static function getColumnTypesMultipleEnabled(){
        return static::getColumnTypes('isMultipleEnabled');
    }

    protected static function getColumnTypes($func){
        $key = sprintf(Define::SYSTEM_KEY_SESSION_CUSTOM_COLUMN_TYPE_FUNC, $func);
        return System::cache($key, function() use($func){
            $columnTypes = [];
            foreach(static::$availableFields as $column_type => $availableField){
                if($availableField::{$func}()){
                    $columnTypes[] = $column_type;
                }
            }
    
            return $columnTypes;
        });
    }    
}