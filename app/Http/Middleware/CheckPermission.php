<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission  The required permission.
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission = [])
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // System Admins have all permissions in RAINBO
        if ($user->isSystemAdmin()) {
            return $next($request);
        }

        // Check RAINBO-specific permissions first
        if ($user->hasRainboPermission($permission)) {
            return $next($request);
        }

        // Legacy: Allow Super Admin regardless of specific permission
        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return $next($request);
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user has the required permission via Spatie
        if ($user->can($permission)) {
            return $next($request);
        }

        // User doesn't have the permission - block access
        abort(403, 'Unauthorized.');
    }
}
