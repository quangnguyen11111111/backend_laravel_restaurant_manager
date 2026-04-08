<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Models\Account;
use App\Models\RefreshToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Exception;

class AuthController extends Controller
{
    const TOKEN_TYPE_ACCESS = 'AccessToken';
    const TOKEN_TYPE_REFRESH = 'RefreshToken';

    /**
     * POST /auth/login
     * Đăng nhập
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::where('email', $validated['email'])->first();

        if (!$account) {
            return response()->json([
                'message' => 'Email không tồn tại',
                'errors' => [
                    ['field' => 'email', 'message' => 'Email không tồn tại']
                ],
                'statusCode' => 422,
            ], 422);
        }

        if (!Hash::check($validated['password'], $account->password)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng',
                'errors' => [
                    ['field' => 'password', 'message' => 'Email hoặc mật khẩu không đúng']
                ],
                'statusCode' => 422,
            ], 422);
        }

        $accessToken = $this->signAccessToken($account->id, $account->role);
        $refreshToken = $this->signRefreshToken($account->id, $account->role);

        $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);
        $refreshTokenExpiresAt = date('Y-m-d H:i:s', $decodedRefreshToken->exp);

        // Save refresh token to database
        RefreshToken::create([
            'token' => $refreshToken,
            'account_id' => $account->id,
            'expires_at' => $refreshTokenExpiresAt,
        ]);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email,
                    'role' => $account->role,
                ],
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
            ]
        ]);
    }

    /**
     * POST /auth/logout
     * Đăng xuất
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        RefreshToken::where('token', $validated['refreshToken'])->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    /**
     * POST /auth/refresh-token
     * Làm mới token
     */
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $refreshToken = $validated['refreshToken'];

        // Verify refresh token
        try {
            $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Refresh token không hợp lệ'
            ], 401);
        }

        // Find refresh token in database
        $refreshTokenDoc = RefreshToken::where('token', $refreshToken)
            ->with('account')
            ->first();

        if (!$refreshTokenDoc) {
            return response()->json([
                'message' => 'Refresh token không tồn tại'
            ], 401);
        }

        $account = $refreshTokenDoc->account;

        // Generate new tokens (keep same expiry for refresh token)
        $newAccessToken = $this->signAccessToken($account->id, $account->role);
        $newRefreshToken = $this->signRefreshToken($account->id, $account->role, $decodedRefreshToken->exp);

        // Delete old refresh token
        RefreshToken::where('token', $refreshToken)->delete();

        // Save new refresh token
        RefreshToken::create([
            'token' => $newRefreshToken,
            'account_id' => $account->id,
            'expires_at' => $refreshTokenDoc->expires_at,
        ]);

        return response()->json([
            'message' => 'Lấy token mới thành công',
            'data' => [
                'accessToken' => $newAccessToken,
                'refreshToken' => $newRefreshToken,
            ]
        ]);
    }

    /**
     * GET /auth/login/google
     * Đăng nhập bằng Google OAuth
     */
    public function loginGoogle(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return $this->redirectWithError('Thiếu authorization code', 400);
        }

        try {
            // Get OAuth token from Google
            $tokenData = $this->getOauthGoogleToken($code);

            // Get Google user info
            $googleUser = $this->getGoogleUser($tokenData['id_token'], $tokenData['access_token']);

            // Check if email is verified
            if (!($googleUser['verified_email'] ?? false)) {
                return $this->redirectWithError('Email chưa được xác minh từ Google', 403);
            }

            // Find account by email
            $account = Account::where('email', $googleUser['email'])->first();

            if (!$account) {
                return $this->redirectWithError('Tài khoản này không tồn tại trên hệ thống website', 403);
            }

            // Generate tokens
            $accessToken = $this->signAccessToken($account->id, $account->role);
            $refreshToken = $this->signRefreshToken($account->id, $account->role);

            // Save refresh token to database
            $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);
            $refreshTokenExpiresAt = date('Y-m-d H:i:s', $decodedRefreshToken->exp);

            RefreshToken::create([
                'token' => $refreshToken,
                'account_id' => $account->id,
                'expires_at' => $refreshTokenExpiresAt,
            ]);

            // Redirect to client with tokens
            $queryString = http_build_query([
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'status' => 200,
            ]);

            return redirect(config('services.google.redirect_client_url') . '?' . $queryString);

        } catch (Exception $e) {
            return $this->redirectWithError($e->getMessage() ?: 'Lỗi không xác định', 500);
        }
    }

    /**
     * Redirect with error to client
     */
    private function redirectWithError(string $message, int $status)
    {
        $queryString = http_build_query([
            'message' => $message,
            'status' => $status,
        ]);

        return redirect(config('services.google.redirect_client_url') . '?' . $queryString);
    }

    /**
     * Get OAuth token from Google
     */
    private function getOauthGoogleToken(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new Exception('Không thể lấy token từ Google');
        }

        return $response->json();
    }

    /**
     * Get Google user info
     */
    private function getGoogleUser(string $idToken, string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$idToken}",
        ])->get('https://www.googleapis.com/oauth2/v1/userinfo', [
            'access_token' => $accessToken,
            'alt' => 'json',
        ]);

        if (!$response->successful()) {
            throw new Exception('Không thể lấy thông tin user từ Google');
        }

        return $response->json();
    }

    /**
     * Sign access token
     */
    private function signAccessToken(int $userId, string $role, ?int $exp = null): string
    {
        $now = time();
        $expiresIn = $exp ?? ($now + $this->parseExpiry(config('auth.access_token_expires_in', '15m')));

        $payload = [
            'userId' => $userId,
            'role' => $role,
            'tokenType' => self::TOKEN_TYPE_ACCESS,
            'iat' => $now,
            'exp' => $expiresIn,
        ];

        return JWT::encode($payload, config('auth.access_token_secret'), 'HS256');
    }

    /**
     * Sign refresh token
     */
    private function signRefreshToken(int $userId, string $role, ?int $exp = null): string
    {
        $now = time();
        $expiresIn = $exp ?? ($now + $this->parseExpiry(config('auth.refresh_token_expires_in', '7d')));

        $payload = [
            'userId' => $userId,
            'role' => $role,
            'tokenType' => self::TOKEN_TYPE_REFRESH,
            'iat' => $now,
            'exp' => $expiresIn,
        ];

        return JWT::encode($payload, config('auth.refresh_token_secret'), 'HS256');
    }

    /**
     * Verify access token
     */
    private function verifyAccessToken(string $token): object
    {
        return JWT::decode($token, new Key(config('auth.access_token_secret'), 'HS256'));
    }

    /**
     * Verify refresh token
     */
    private function verifyRefreshToken(string $token): object
    {
        return JWT::decode($token, new Key(config('auth.refresh_token_secret'), 'HS256'));
    }

    /**
     * Parse expiry string (e.g., '15m', '7d', '1h') to seconds
     */
    private function parseExpiry(string $expiry): int
    {
        $unit = substr($expiry, -1);
        $value = (int) substr($expiry, 0, -1);

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => $value,
        };
    }
}
