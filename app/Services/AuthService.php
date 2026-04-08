<?php

namespace App\Services;

use App\Exceptions\AuthServiceException;
use App\Repositories\Contracts\AuthRepositoryInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuthService
{
    private const TOKEN_TYPE_ACCESS = 'AccessToken';
    private const TOKEN_TYPE_REFRESH = 'RefreshToken';

    public function __construct(
        private readonly AuthRepositoryInterface $authRepository
    ) {
    }

    /**
     * @param array<string, string> $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $account = $this->authRepository->findAccountByEmail($credentials['email']);

        if (!$account) {
            throw new AuthServiceException(
                'Email không tồn tại',
                422,
                [
                    ['field' => 'email', 'message' => 'Email không tồn tại'],
                ]
            );
        }

        if (!Hash::check($credentials['password'], $account->password)) {
            throw new AuthServiceException(
                'Email hoặc mật khẩu không đúng',
                422,
                [
                    ['field' => 'password', 'message' => 'Email hoặc mật khẩu không đúng'],
                ]
            );
        }

        $accessToken = $this->signAccessToken($account->id, $account->role);
        $refreshToken = $this->signRefreshToken($account->id, $account->role);
        $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);

        $this->authRepository->createRefreshToken(
            $refreshToken,
            $account->id,
            date('Y-m-d H:i:s', $decodedRefreshToken->exp)
        );

        return [
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
            ],
        ];
    }

    public function logout(string $refreshToken): array
    {
        $this->authRepository->deleteRefreshToken($refreshToken);

        return [
            'message' => 'Đăng xuất thành công',
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        try {
            $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);
        } catch (Throwable) {
            throw new AuthServiceException('Refresh token không hợp lệ', 401);
        }

        $refreshTokenDoc = $this->authRepository->findRefreshTokenWithAccount($refreshToken);

        if (!$refreshTokenDoc || !$refreshTokenDoc->account) {
            throw new AuthServiceException('Refresh token không tồn tại', 401);
        }

        $account = $refreshTokenDoc->account;

        $newAccessToken = $this->signAccessToken($account->id, $account->role);
        $newRefreshToken = $this->signRefreshToken(
            $account->id,
            $account->role,
            $decodedRefreshToken->exp
        );

        $this->authRepository->deleteRefreshToken($refreshToken);
        $this->authRepository->createRefreshToken(
            $newRefreshToken,
            $account->id,
            $refreshTokenDoc->expires_at->format('Y-m-d H:i:s')
        );

        return [
            'message' => 'Lấy token mới thành công',
            'data' => [
                'accessToken' => $newAccessToken,
                'refreshToken' => $newRefreshToken,
            ],
        ];
    }

    public function loginGoogle(?string $code): string
    {
        if (!$code) {
            throw new AuthServiceException('Thiếu authorization code', 400);
        }

        $tokenData = $this->getOauthGoogleToken($code);
        $googleUser = $this->getGoogleUser($tokenData['id_token'], $tokenData['access_token']);

        if (!($googleUser['verified_email'] ?? false)) {
            throw new AuthServiceException('Email chưa được xác minh từ Google', 403);
        }

        $account = $this->authRepository->findAccountByEmail($googleUser['email']);

        if (!$account) {
            throw new AuthServiceException('Tài khoản này không tồn tại trên hệ thống website', 403);
        }

        $accessToken = $this->signAccessToken($account->id, $account->role);
        $refreshToken = $this->signRefreshToken($account->id, $account->role);
        $decodedRefreshToken = $this->verifyRefreshToken($refreshToken);

        $this->authRepository->createRefreshToken(
            $refreshToken,
            $account->id,
            date('Y-m-d H:i:s', $decodedRefreshToken->exp)
        );

        return $this->buildGoogleRedirectUrl([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'status' => 200,
        ]);
    }

    public function buildGoogleErrorRedirectUrl(AuthServiceException $exception): string
    {
        return $this->buildGoogleRedirectUrl([
            'message' => $exception->getMessage(),
            'status' => $exception->getStatusCode(),
        ]);
    }

    /**
     * @return array<string, mixed>
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
            throw new AuthServiceException('Không thể lấy token từ Google', 502);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    /**
     * @return array<string, mixed>
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
            throw new AuthServiceException('Không thể lấy thông tin user từ Google', 502);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildGoogleRedirectUrl(array $query): string
    {
        return config('services.google.redirect_client_url') . '?' . http_build_query($query);
    }

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

    private function verifyRefreshToken(string $token): object
    {
        $decoded = JWT::decode($token, new Key(config('auth.refresh_token_secret'), 'HS256'));

        if (($decoded->tokenType ?? null) !== self::TOKEN_TYPE_REFRESH) {
            throw new AuthServiceException('Refresh token không hợp lệ', 401);
        }

        return $decoded;
    }

    private function parseExpiry(string $expiry): int
    {
        $unit = substr($expiry, -1);
        $value = (int) substr($expiry, 0, -1);

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => (int) $expiry,
        };
    }
}
