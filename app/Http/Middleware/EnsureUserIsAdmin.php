<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Dozvoli pristup samo korisnicima sa role === 'admin'.
     * Mora ići POSLE 'auth' middleware-a (gost dobija redirect na login od 'auth').
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
