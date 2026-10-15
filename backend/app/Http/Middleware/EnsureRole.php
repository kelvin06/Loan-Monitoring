<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user() && $request->user()->role === $role && $request->user()->is_active, 403, 'Forbidden.');
        return $next($request);
    }
}
