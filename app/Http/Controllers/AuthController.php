<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthServiceException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RefreshTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    /**
     * POST /auth/login
     * Đăng nhập
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return response()->json($result);
        } catch (AuthServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /auth/logout
     * Đăng xuất
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $result = $this->authService->logout($request->validated('refreshToken'));

        return response()->json($result);
    }

    /**
     * POST /auth/refresh-token
     * Làm mới token
     */
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken($request->validated('refreshToken'));

            return response()->json($result);
        } catch (AuthServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * GET /auth/login/google
     * Đăng nhập bằng Google OAuth
     */
    public function loginGoogle(Request $request)
    {
        try {
            $redirectUrl = $this->authService->loginGoogle($request->query('code'));

            return redirect($redirectUrl);
        } catch (AuthServiceException $exception) {
            return redirect($this->authService->buildGoogleErrorRedirectUrl($exception));
        } catch (Throwable $exception) {
            return redirect($this->authService->buildGoogleErrorRedirectUrl(
                new AuthServiceException($exception->getMessage() ?: 'Lỗi không xác định', 500)
            ));
        }
    }

    private function jsonErrorResponse(AuthServiceException $exception): JsonResponse
    {
        $payload = [
            'message' => $exception->getMessage(),
            'statusCode' => $exception->getStatusCode(),
        ];

        if ($exception->getErrors() !== []) {
            $payload['errors'] = $exception->getErrors();
        }

        return response()->json($payload, $exception->getStatusCode());
    }
}
