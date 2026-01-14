<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API/JWT: never redirect, always return 401 JSON
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        // If you later build web pages with a login route, you can keep this
        return route('login');
    }

}
