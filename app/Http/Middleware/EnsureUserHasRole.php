<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            abort(403, 'Unauthorized access.');
        }

        $allRoles = [];
        foreach ($roles as $role) {
            if (str_contains($role, '|')) {
                $allRoles = array_merge($allRoles, explode('|', $role));
            } else {
                $allRoles[] = $role;
            }
        }

        if (! $request->user()->hasAnyRole($allRoles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
