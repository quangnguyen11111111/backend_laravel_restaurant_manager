<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\CreateGuestRequest;
use App\Http\Requests\GetGuestListRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly GuestService $guestService
    ) {
    }

    /**
     * GET /accounts
     * Lấy danh sách tất cả tài khoản
     */
    public function index(): JsonResponse
    {
        $result = $this->accountService->index();

        return response()->json($result);
    }

    /**
     * POST /accounts
     * Tạo tài khoản nhân viên mới
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $result = $this->accountService->store($request->validated());

        return response()->json($result);
    }

    /**
     * GET /accounts/detail/{id}
     * Lấy thông tin chi tiết nhân viên
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->accountService->show($id);

        return response()->json($result);
    }

    /**
     * PUT /accounts/detail/{id}
     * Cập nhật thông tin nhân viên
     */
    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->accountService->update($id, $request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * DELETE /accounts/detail/{id}
     * Xóa tài khoản nhân viên
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->accountService->destroy($id);

        return response()->json($result);
    }

    /**
     * GET /accounts/me
     * Lấy thông tin tài khoản hiện tại
     */
    public function me(Request $request): JsonResponse
    {
        $result = $this->accountService->me($request->user());

        return response()->json($result);
    }

    /**
     * PUT /accounts/me
     * Cập nhật thông tin cá nhân
     */
    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $result = $this->accountService->updateMe($request->user(), $request->validated());

        return response()->json($result);
    }

    /**
     * PUT /accounts/change-password
     * Đổi mật khẩu
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->accountService->changePassword($request->user(), $request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /accounts/guests
     * Tạo tài khoản khách
     */
    public function createGuest(CreateGuestRequest $request): JsonResponse
    {
        try {
            $result = $this->guestService->createGuest($request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * GET /accounts/guests
     * Lấy danh sách khách
     */
    public function getGuests(GetGuestListRequest $request): JsonResponse
    {
        $result = $this->guestService->getGuests($request->validated());

        return response()->json($result);
    }

    private function jsonErrorResponse(ServiceException $exception): JsonResponse
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
