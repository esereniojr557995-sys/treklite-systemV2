<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more roles.
 *
 * Usage in routes/web.php:
 *   Route::middleware('role:owner,manager')->group(function () { ... });
 *
 * This is what implements the "Implement role-based access" objective:
 * Owner/Manager get full access; Staff is limited to their own branch's
 * sales screens (enforced again inside SaleController for defense-in-depth).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
