<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip middleware for login page
        if ($request->routeIs('filament.admin.auth.login')) {
            return $next($request);
        }

        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('filament.admin.auth.login');
        }

        try {
            // Check if user has either admin or customer role
            $hasRole = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', Auth::id())
                ->whereIn('roles.name', ['admin', 'customer'])
                ->exists();

            if (!$hasRole) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Unauthorized. You need proper role to access this area.'], 403);
                }
                return redirect()->route('filament.admin.auth.login');
            }

            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('AdminMiddleware Error: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Internal Server Error'], 500);
            }
            return redirect()->route('filament.admin.auth.login');
        }
    }
}
