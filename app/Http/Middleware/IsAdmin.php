<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden',
                'statusCode' => 403,
                'error' => true,
                'data' => [],
            ], 403);
        }

        return $next($request);
    }
}
