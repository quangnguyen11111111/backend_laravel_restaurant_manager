<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\CreateGuestRequest;
use App\Http\Requests\GetGuestListRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Models\Account;
use App\Models\Guest;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    /**
     * GET /accounts
     * Lấy danh sách tất cả tài khoản
     */
    public function index(): JsonResponse
    {
        $accounts = Account::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email,
                    'role' => $account->role,
                    'avatar' => $account->avatar,
                ];
            });

        return response()->json([
            'data' => $accounts,
            'message' => 'Lấy danh sách nhân viên thành công'
        ]);
    }

    /**
     * POST /accounts
     * Tạo tài khoản nhân viên mới
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'avatar' => $validated['avatar'] ?? null,
            'role' => Account::ROLE_EMPLOYEE,
        ]);

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Tạo tài khoản thành công'
        ]);
    }

    /**
     * GET /accounts/detail/{id}
     * Lấy thông tin chi tiết nhân viên
     */
    public function show(int $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Lấy thông tin nhân viên thành công'
        ]);
    }

    /**
     * PUT /accounts/detail/{id}
     * Cập nhật thông tin nhân viên
     */
    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json([
                'message' => 'Tài khoản bạn đang cập nhật không còn tồn tại nữa!',
                'errors' => [
                    ['field' => 'email', 'message' => 'Tài khoản bạn đang cập nhật không còn tồn tại nữa!']
                ]
            ], 422);
        }

        $validated = $request->validated();
        $isChangeRole = $account->role !== ($validated['role'] ?? $account->role);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar' => $validated['avatar'] ?? $account->avatar,
            'role' => $validated['role'] ?? $account->role,
        ];

        // Update password if changePassword is true
        if (!empty($validated['changePassword']) && !empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $account->update($updateData);

        // TODO: Socket emit 'refresh-token' if isChangeRole && socketId exists

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Cập nhật thành công'
        ]);
    }

    /**
     * DELETE /accounts/detail/{id}
     * Xóa tài khoản nhân viên
     */
    public function destroy(int $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        // TODO: Get socketId before deleting for socket emit

        $accountData = [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'role' => $account->role,
            'avatar' => $account->avatar,
        ];

        $account->delete();

        // TODO: Socket emit 'logout' if socketId exists

        return response()->json([
            'data' => $accountData,
            'message' => 'Xóa thành công'
        ]);
    }

    /**
     * GET /accounts/me
     * Lấy thông tin tài khoản hiện tại
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Lấy thông tin thành công'
        ]);
    }

    /**
     * PUT /accounts/me
     * Cập nhật thông tin cá nhân
     */
    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $account = $request->user();
        $validated = $request->validated();

        $account->update([
            'name' => $validated['name'],
            'avatar' => $validated['avatar'] ?? $account->avatar,
        ]);

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Cập nhật thông tin thành công'
        ]);
    }

    /**
     * PUT /accounts/change-password
     * Đổi mật khẩu
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $account = $request->user();
        $validated = $request->validated();

        // Check if old password is correct
        if (!Hash::check($validated['oldPassword'], $account->password)) {
            return response()->json([
                'message' => 'Mật khẩu cũ không đúng',
                'errors' => [
                    ['field' => 'oldPassword', 'message' => 'Mật khẩu cũ không đúng']
                ]
            ], 422);
        }

        $account->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'avatar' => $account->avatar,
            ],
            'message' => 'Đổi mật khẩu thành công'
        ]);
    }

    /**
     * POST /accounts/guests
     * Tạo tài khoản khách
     */
    public function createGuest(CreateGuestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check if table exists
        $table = Table::where('number', $validated['tableNumber'])->first();

        if (!$table) {
            return response()->json([
                'message' => 'Bàn không tồn tại',
            ], 400);
        }

        // Check if table is hidden
        if ($table->status === 'Hidden') {
            return response()->json([
                'message' => "Bàn {$table->number} đã bị ẩn, vui lòng chọn bàn khác",
            ], 400);
        }

        $guest = Guest::create([
            'name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
        ]);

        return response()->json([
            'message' => 'Tạo tài khoản khách thành công',
            'data' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'role' => Guest::ROLE_GUEST,
                'tableNumber' => $guest->table_number,
                'createdAt' => $guest->created_at,
                'updatedAt' => $guest->updated_at,
            ],
        ]);
    }

    /**
     * GET /accounts/guests
     * Lấy danh sách khách
     */
    public function getGuests(GetGuestListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Guest::orderBy('created_at', 'desc');

        if (!empty($validated['fromDate'])) {
            $query->where('created_at', '>=', $validated['fromDate']);
        }

        if (!empty($validated['toDate'])) {
            $query->where('created_at', '<=', $validated['toDate']);
        }

        $guests = $query->get()->map(function ($guest) {
            return [
                'id' => $guest->id,
                'name' => $guest->name,
                'tableNumber' => $guest->table_number,
                'createdAt' => $guest->created_at,
                'updatedAt' => $guest->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách khách thành công',
            'data' => $guests,
        ]);
    }
}
