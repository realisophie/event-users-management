<?php

namespace App\Http\Middleware;

use Closure;

class CheckVerifyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!auth()->user()->hasVerifiedEmail() && !auth()->user()->verified) {
            return redirect()->route('eventmanager.dashboard')->with([
                'type' => 'warning',
                'title' => 'Verify Email',
                'message' => 'Verify your email to continue using ZMSEND'
            ]);
        }
        return $next($request);
    }
}
