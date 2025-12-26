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

        // Allow Super Admin regardless of specific permission (like RAI)
        // This is checked FIRST - super admins bypass all permission checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // System Admins have all permissions in RAIOPS (via role)
        if ($user->isSystemAdmin()) {
            return $next($request);
        }

        // Check RAIOPS-specific permissions
        if ($user->hasRaiOpsPermission($permission)) {
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
