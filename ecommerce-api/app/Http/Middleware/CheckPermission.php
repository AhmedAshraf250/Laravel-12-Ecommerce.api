<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (! $request->user()) {
            throw UnauthorizedException::notLoggedIn();
        }

        if (! $request->user()->can($permission)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.'
            ], 403);
        }

        return $next($request);
    }
}
