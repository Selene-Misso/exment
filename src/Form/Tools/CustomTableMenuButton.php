<?php

namespace Exceedone\Exment\Form\Tools;

use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomTable;
use Encore\Admin\Facades\Admin;

/**
 * Custom Table Menu
 */
class CustomTableMenuButton extends ModalTileMenuButton
{
    protected $page_name;
    protected $page_name_sub;
    protected $custom_table;
    
    public function __construct($page_name, $custom_table, $page_name_sub = null)
    {
        $this->page_name = $page_name;
        $this->custom_table = $custom_table;
        $this->page_name_sub = $page_name_sub;

        parent::__construct([
            'label' => exmtrans("change_page_menu.change_page_label"),
            'icon' => 'fa-cog',
            'button_class' => 'btn-default',
        ]);
        $this->modal_title = exmtrans("change_page_menu.change_page_label");
    }

    public function render()
    {
        $items = [];
        foreach (Define::GRID_CHANGE_PAGE_MENULIST as $menu) {
            // if same page, skip
            if ($this->page_name == array_get($menu, 'name') && !array_has($menu, 'buttons')) {
                continue;
            }

            // check menu using role
            // if this page_name is table and grid, check role
            if ($this->page_name == 'table' && !isset($this->custom_table)) {
                // if user dont't has role system
                if (!Admin::user()->hasPermission(array_get($menu, 'roles'))) {
                    continue;
                }
            } elseif (array_get($menu, 'name') == 'view' && !$this->custom_table->hasViewPermission()) {
                continue;
            } else {
                // if user dont't has role as table
                if (!$this->custom_table->hasPermission(array_get($menu, 'roles'))) {
                    continue;
                }
            }

            $url = str_replace(':id', $this->custom_table->id, array_get($menu, 'href'));
            $url = str_replace(':table_name', $this->custom_table->table_name, $url);


            // get buttons
            $buttons = collect(array_get($menu, 'buttons', []))
                ->filter(function($button){
                    return array_get($button, 'name') != $this->page_name_sub;
                })
                ->map(function($button){
                    return [
                        'icon' => array_get($button, 'icon'),
                        'label' => exmtrans(array_get($button, 'exmtrans')),
                        'href' => admin_url(str_replace(':id', $this->custom_table->id, array_get($button, 'href'))),
                    ];
                });


            $items[] = [
                'href' => admin_url($url),
                'icon' => array_get($menu, 'icon'),
                'header' => exmtrans(array_get($menu, 'exmtrans')),
                'description' => exmtrans(array_get($menu, 'description')),
                'buttons' => $buttons,
            ];
        }

        // if no menu, return
        if (count($items) == 0) {
            return null;
        }

        $this->groups = [[
            'items' => $items
        ]];

        return parent::render();
    }
}
