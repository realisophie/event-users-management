<?php

namespace App\Http\Middleware;

use Closure;

class CheckEventMiddleware
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
        $user = $request->user()->loadCount('zoomMeetings');
        if ($user->plan->allowed_events != 0 && $user->zoom_meetings_count >= $user->plan->allowed_events) {
            return redirect()->route('eventmanager.event.index')->with([
                'type' => 'warning',
                'title' => 'Event Limit Exceeded',
                'message' => 'You have exceeded the limit of events at a time.',
                'exceeded' => true
            ]);
        }
        return $next($request);
    }
}
