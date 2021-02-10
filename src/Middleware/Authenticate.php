<?php

namespace Exceedone\Exment\Middleware;

use Closure;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Define;

class Authenticate extends \Encore\Admin\Middleware\Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Get System config
        $shouldPassThrough = shouldPassThrough(false);
        if ($shouldPassThrough) {
            return $next($request);
        }
        
        // Get System config
        $initialized = System::initialized();
        // if path is not "initialize" and not installed, then redirect to initialize
        if (!shouldPassThrough(true) && !$initialized) {
            $request->session()->invalidate();
            return redirect()->guest(admin_base_path('initialize'));
        }

        $user = \Admin::user();
        if (is_null($user) || is_null($user->base_user)) {
            return redirect()->guest(admin_base_path('auth/login'));
        }
        \Auth::shouldUse(Define::AUTHENTICATE_KEY_WEB);

        return $next($request);
    }
}
