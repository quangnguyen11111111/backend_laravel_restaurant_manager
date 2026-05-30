<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Guest;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use Firebase\JWT\ExpiredException;

class JwtAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'message' => 'Không nhận được access token'
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key(config('auth.access_token_secret'), 'HS256'));

            // Verify token type
            if (($decoded->tokenType ?? null) !== 'AccessToken') {
                return response()->json([
                    'message' => 'Access token không hợp lệ'
                ], 401);
            }

            $role = $decoded->role ?? null;

            if ($role === Guest::ROLE_GUEST) {
                $guest = Guest::find($decoded->userId);
                if (!$guest) {
                    return response()->json([
                        'message' => 'Guest không tồn tại'
                    ], 401);
                }

                $request->setUserResolver(function () use ($guest) {
                    return $guest;
                });
            } else {
                // Find account and set to request
                $account = Account::find($decoded->userId);

                if (!$account) {
                    return response()->json([
                        'message' => 'Tài khoản không tồn tại'
                    ], 401);
                }

                $request->setUserResolver(function () use ($account) {
                    return $account;
                });
            }

            // Also set decoded token for reference
            $request->attributes->set('decodedAccessToken', $decoded);
        } catch (ExpiredException $e) {
            return response()->json([
                'message' => 'Access token đã hết hạn',
                'code' => 'TOKEN_EXPIRED'
            ], 401);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Access token không hợp lệ'
            ], 401);
        }

        return $next($request);
    }
}
