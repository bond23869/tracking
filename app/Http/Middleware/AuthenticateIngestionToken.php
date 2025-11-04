<?php

namespace App\Http\Middleware;

use App\Models\IngestionToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIngestionToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid Authorization header',
            ], 401);
        }

        $token = substr($authorization, 7); // Remove "Bearer "

        // Extract prefix (first 12 characters)
        if (strlen($token) < 12) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid token format',
            ], 401);
        }

        $tokenPrefix = substr($token, 0, 12);
        $tokenSecret = substr($token, 13); // Skip the dot after prefix

        // Find token by prefix
        $ingestionToken = IngestionToken::where('token_prefix', $tokenPrefix)
            ->whereNull('revoked_at')
            ->first();

        if (!$ingestionToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid token',
            ], 401);
        }

        // Check expiry
        if ($ingestionToken->expires_at && $ingestionToken->expires_at->isPast()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token has expired',
            ], 401);
        }

        // Verify token hash
        $fullToken = $tokenPrefix . '.' . $tokenSecret;
        if (!Hash::check($fullToken, $ingestionToken->token_hash)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid token',
            ], 401);
        }

        // Check IP allowlist if configured
        if ($ingestionToken->ip_allowlist) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $ingestionToken->ip_allowlist)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'IP address not allowed',
                ], 403);
            }
        }

        // Update last used timestamp
        $ingestionToken->update(['last_used_at' => now()]);

        // Attach token and website to request
        $request->merge([
            'ingestion_token' => $ingestionToken,
            'website' => $ingestionToken->website,
        ]);

        return $next($request);
    }
}
