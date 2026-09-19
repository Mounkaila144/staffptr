<?php

namespace App\Http\Middleware;

use App\Models\Identity\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User
            || ! $user->must_change_password
            || $request->routeIs('password.change.edit', 'password.change.update', 'logout')) {
            return $next($request);
        }

        $location = route('password.change.edit');

        if ($request->header('X-Inertia') === 'true') {
            return Inertia::location($location);
        }

        return redirect()->to($location);
    }
}
