<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateQueryToken
{
    /**
     * Handle an incoming request.
     * Converts query parameter ?token=... into Authorization: Bearer ... header for browser file downloads.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && !$request->headers->has('Authorization')) {
            $token = trim($request->query('token'));
            if (!empty($token)) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
