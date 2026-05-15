<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage in routes: ->middleware('role:hr_manager,admin')
     * Passes if the user has ANY of the listed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole($roles)) {
            abort(403, 'لا تملك الصلاحية للوصول إلى هذه الصفحة');
        }
        return $next($request);
    }
}
