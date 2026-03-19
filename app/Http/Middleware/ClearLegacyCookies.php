<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearLegacyCookies
{
    /**
     * Expire the old "laravel-session" cookie so browsers stop sending it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->cookies->has('laravel-session')) {
            $response->headers->setCookie(
                cookie('laravel-session', '', -1)
            );
        }

        return $response;
    }
}
