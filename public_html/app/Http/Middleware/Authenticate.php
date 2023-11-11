<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        $plan='';
        if(request('plan')){
            $plan = request('plan');
            $request->session()->put('plan', $plan);
        }
        if(request('redemption') && (request('redemption') == 1)){
            $request->session()->put('redemption', 1);
            return route('register');
            //return route('register',['redemption' => request('redemption'), 'plan' => $plan]);
        }elseif (! $request->expectsJson()) {
            $request->session()->forget('redemption');
            return route('login');
        }
    }
}
