<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddStudentDiscountCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origin = rtrim(strtolower((string) $request->header('Origin')), '/');

        if (preg_match('#^https://[a-z0-9][a-z0-9.-]+$#', $origin) === 1) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
