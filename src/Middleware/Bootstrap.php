<?php

namespace Exceedone\Exment\Middleware;

use Illuminate\Http\Request;
use Encore\Admin\Facades\Admin as Ad;
use Exceedone\Exment\Controllers;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Enums\PluginType;

/**
 * Middleware as Bootstrap.
 * Setup for display. ex. set css, js, ...
 */
class Bootstrap
{
    use BootstrapTrait;

    public function handle(Request $request, \Closure $next)
    {
        $this->setCssJs($request, $next);

        return $next($request);
    }

    /**
     * Set css and js. only first request(not ajax and pjax)
     *
     * @param Request $request
     * @param \Closure $next
     * @return void
     */
    protected function setCssJs(Request $request, \Closure $next)
    {
        if ($request->ajax() || $request->pjax()) {
            return;
        }

        if ($this->isStaticRequest($request)) {
            return;
        }

        Ad::navbar(function (\Encore\Admin\Widgets\Navbar $navbar) {
            $navbar->left(Controllers\SearchController::renderSearchHeader());
            $navbar->left(new \Exceedone\Exment\Form\Navbar\Hidden);
            $navbar->right(new \Exceedone\Exment\Form\Navbar\HelpNav);
            $navbar->right(new \Exceedone\Exment\Form\Navbar\NotifyNav);
        });
        Ad::js(asset('lib/js/jquery-ui.min.js'));
        Ad::css(asset('lib/css/jquery-ui.min.css'));

        Ad::js(asset('lib/js/bignumber.min.js'));

        $this->setCssJsList([
            'vendor/exment/fullcalendar/core/main.min.css',
            'vendor/exment/fullcalendar/daygrid/main.min.css',
            'vendor/exment/fullcalendar/list/main.min.css',
            'vendor/exment/fullcalendar/timegrid/main.min.css',
            'vendor/exment/css/common.css',
            'vendor/exment/css/workflow.css',
            'vendor/exment/css/customform.css',
            'vendor/exment/codemirror/codemirror.css',
            'vendor/exment/jstree/themes/default/style.min.css',
        ], true);

        $this->setCssJsList([
            'vendor/exment/validation/jquery.validate.js',
            'vendor/exment/chartjs/chart.min.js',
            'vendor/exment/codemirror/codemirror.js',
            'vendor/exment/codemirror/mode/htmlmixed/htmlmixed.js',
            'vendor/exment/codemirror/mode/xml/xml.js',
            'vendor/exment/codemirror/mode/javascript/javascript.js',
            'vendor/exment/codemirror/mode/css/css.js',
            'vendor/exment/codemirror/mode/php/php.js',
            'vendor/exment/codemirror/mode/clike/clike.js',
            'vendor/exment/jquery/jquery.color.min.js',
            'vendor/exment/mathjs/math.min.js',
            'vendor/exment/js/numberformat.js',
            'vendor/exment/fullcalendar/core/main.min.js',
            'vendor/exment/fullcalendar/core/locales-all.min.js',
            'vendor/exment/fullcalendar/interaction/main.min.js',
            'vendor/exment/fullcalendar/daygrid/main.min.js',
            'vendor/exment/fullcalendar/list/main.min.js',
            'vendor/exment/fullcalendar/timegrid/main.min.js',
            'vendor/exment/jstree/jstree.min.js',
            'vendor/exment/js/common_all.js',
            'vendor/exment/js/common.js',
            'vendor/exment/js/search.js',
            'vendor/exment/js/calc.js',
            'vendor/exment/js/notify_navbar.js',
            'vendor/exment/js/modal.js',
            'vendor/exment/js/workflow.js',
            'vendor/exment/js/changefield.js',
            'vendor/exment/js/customformitem.js',
            'vendor/exment/js/customform.js',
            'vendor/exment/js/preview.js',
            'vendor/exment/js/webapi.js',
            'vendor/exment/js/admin.webapi.js',
            'vendor/exment/js/getbox.js',
            'vendor/exment/js/admin.getbox.js',
        ], false);

        // set scripts
        $pluginPublics = Plugin::getPluginPublics();
        foreach ($pluginPublics as $pluginPublic) {
            // get scripts
            $plugin = $pluginPublic->_plugin();
            $p = $plugin->matchPluginType(PluginType::SCRIPT) ? 'js' : 'css';
            $cdns = array_get($plugin, 'options.cdns', []);
            foreach ($cdns as $cdn) {
                Ad::{$p.'last'}($cdn);
            }

            // get each scripts
            $items = collect($pluginPublic->{$p}(true))->map(function ($item) use ($pluginPublic) {
                return admin_urls($pluginPublic->_plugin()->getRouteUri(), 'public/', $item);
            });
            if (!empty($items)) {
                foreach ($items as $item) {
                    Ad::{$p.'last'}($item);
                }
            }
        }

        // set Plugin resource
        $pluginPages = Plugin::getPluginPages();
        foreach ($pluginPages as $pluginPage) {
            // get css and js
            $publics = ['css', 'js'];
            foreach ($publics as $p) {
                $items = collect($pluginPage->{$p}())->map(function ($item) use ($pluginPage) {
                    return admin_urls($pluginPage->_plugin()->getRouteUri(), 'public/', $item);
                });
                if (!empty($items)) {
                    foreach ($items as $item) {
                        Ad::{$p.'last'}($item);
                    }
                }
            }
        }

        // get exment version
        $ver = \Exment::getExmentCurrentVersion();
        if (!isset($ver)) {
            $ver = date('YmdHis');
        }
        Ad::jslast(asset('vendor/exment/js/customscript.js?ver='.$ver));

        // delete object
        $delete_confirm = trans('admin.delete_confirm');
        $confirm = trans('admin.confirm');
        $cancel = trans('admin.cancel');
        
        $script = <<<EOT
    ///// delete click event
    $(document).off('click', '[data-exment-delete]').on('click', '[data-exment-delete]', {}, function(ev){
        ev.preventDefault();

        // get url
        let url = $(ev.target).closest('[data-exment-delete]').data('exment-delete');
        
        Exment.CommonEvent.ShowSwal(url, {
            title: "$delete_confirm",
            confirm:"$confirm",
            method: 'delete',
            cancel:"$cancel",
        });
    });

EOT;
        Ad::script($script);
    }
}
