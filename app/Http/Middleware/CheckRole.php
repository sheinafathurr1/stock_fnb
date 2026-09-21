<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Check if user's role matches (case-insensitive)
        if (strtolower($request->user()->role) !== strtolower($role)) {
            return redirect('/')
                ->with('error', 'Access denied. This page is only accessible to Managers.');
        }

        return $next($request);
    }
}

