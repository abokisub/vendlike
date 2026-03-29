<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class TokenAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        $token = null;

        if ($authHeader) {
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            } elseif (strpos($authHeader, 'Token ') === 0) {
                $token = substr($authHeader, 6);
            } else {
                $token = $authHeader;
            }
        }

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No authentication token provided / Authentication required'
            ], 401);
        }

        // First try Sanctum authentication (for multi-device support)
        $user = null;

        // Check for Sanctum token (ID|SECRET format)
        if (strpos($token, '|') !== false) {
            $parts = explode('|', $token, 2);
            $tokenId = $parts[0];
            $tokenPlainText = $parts[1];

            $sanctumToken = \DB::table('personal_access_tokens')
                ->where('id', $tokenId)
                ->where('tokenable_type', 'App\\Models\\User')
                ->first();

            if ($sanctumToken && hash_equals($sanctumToken->token, hash('sha256', $tokenPlainText))) {
                // Check if token is not expired
                if ($sanctumToken->expires_at && now()->isAfter($sanctumToken->expires_at)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Session expired. Please log in again.'
                    ], 401);
                }
                $user = User::find($sanctumToken->tokenable_id);
            }
        }

        // Fallback to legacy token columns for backward compatibility
        if (!$user) {
            $user = User::where('app_key', $token)
                ->orWhere('habukhan_key', $token)
                ->orWhere('apikey', $token)
                ->first();
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired session. Please log in again.'
            ], 401);
        }

        // Set the user on the request so $request->user() works in controllers
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
