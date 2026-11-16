<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only admins may continue (used for features that touch the server's disk). */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'Only the site admin can do this.');
            }

            return redirect()->route('courses.index')
                ->with('status', 'Only the site admin can add courses from this computer.');
        }

        return $next($request);
    }
}
