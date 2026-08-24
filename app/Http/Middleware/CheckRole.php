<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', [], 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse("Account is not active. Current status: {$user->status}.", [], 403);
        }

        if (!in_array($user->role, $roles)) {
            return $this->errorResponse('Forbidden. You do not have the required permissions.', [], 403);
        }

        return $next($request);
    }
}
