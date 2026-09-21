<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Pages/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Check user role after authentication. Baristas report from the
        // public page and never sign in, so only managers get a session.
        $user = Auth::user();

        if ($user && strtolower((string) $user->role) === 'manager') {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Credentials were right but the role is not manager, so drop the
        // session again. Say which it is: a blank role on an account carried
        // over from another app reads exactly like a wrong password
        // otherwise.
        $role = trim((string) $user?->role);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to '/' rather than route('login'): that route is itself a
        // redirect to '/', and the extra hop consumes the flashed errors, so
        // the message below never reached the page and the rejection looked
        // like a silent failure.
        return redirect('/')->withErrors([
            'username' => $role === ''
                ? 'This account has no role set, so it cannot sign in. A manager role is required.'
                : "Access denied. This account's role is \"{$role}\"; only managers can sign in.",
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
