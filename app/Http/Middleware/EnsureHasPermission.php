<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Handle an incoming request.
     *
     * Accepts one or more comma-separated permission keys — the request
     * passes if the user holds ANY of them (or is a super admin, who
     * bypasses permission checks entirely). Used where a single route is
     * shared by more than one admin "lens" on the same data — e.g. the
     * orders list is read by the Dashboard, Payments, and Reports pages,
     * each gated by its own permission.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$required): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'This action is unauthorized.');
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        $granted = $user->permission_keys->all();

        if (! array_intersect($required, $granted)) {
            abort(403, 'You do not have permission to do this.');
        }

        return $next($request);
    }
}
