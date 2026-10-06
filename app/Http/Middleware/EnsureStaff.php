<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the internal operations portal to admin / staff accounts.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->guest(route('admin.login'));
        }
        abort_unless($user->isStaffAccount() && $user->status === 'active', 403);
        return $next($request);
    }
}
