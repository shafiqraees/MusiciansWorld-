<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API responses run deferred work (welcome emails) after the response is sent.
 * Without an explicit length the client keeps the socket open until that work
 * finishes, so the browser only resolves the fetch minutes later.
 */
class SetContentLength
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
            if (! $response->headers->has('Content-Length') && ! $response->headers->has('Transfer-Encoding')) {
                $response->headers->set('Content-Length', (string) strlen($response->getContent()));
            }
        }

        return $response;
    }
}
