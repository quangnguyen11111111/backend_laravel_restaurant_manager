<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestCreateOrdersRequest;
use App\Http\Requests\GuestLoginRequest;
use App\Repositories\GuestRepository;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Services\GuestService;
use App\Services\OrderService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GuestController extends Controller
{
    public function __construct(
        private readonly GuestService $guestService,
        private readonly GuestRepositoryInterface $guestRepository,
        private readonly OrderService $orderService
    ) {}

    public function login(GuestLoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $table = \App\Models\Table::query()
                ->where('number', $validated['tableNumber'])
                ->where('token', $validated['token'])
                ->first();

            if (!$table) {
                return response()->json(['message' => 'Bàn không tồn tại hoặc mã token không đúng'], 400);
            }

            if ($table->status === \App\Models\Table::STATUS_HIDDEN) {
                return response()->json(['message' => 'Bàn này đã bị ẩn, hãy chọn bàn khác để đăng nhập'], 400);
            }

            if ($table->status === \App\Models\Table::STATUS_RESERVED) {
                return response()->json(['message' => 'Bàn đã được đặt trước, hãy liên hệ nhân viên để được hỗ trợ'], 400);
            }

            $guest = $this->guestRepository->create([
                'name' => $validated['name'],
                // Guest is not linked to an order until they host-open or guest-join
            ]);

            $tokens = $this->guestService->generateTokens($guest);
            $accessToken = $tokens['accessToken'];
            $refreshToken = $tokens['refreshToken'];

            $activeOrder = $this->orderService->getActiveOrderForTable($validated['tableNumber']);

            return response()->json([
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'guest' => [
                        'id' => $guest->id,
                        'name' => $guest->name,
                        'role' => \App\Models\Guest::ROLE_GUEST,
                        'orderId' => $guest->order_id,
                        'createdAt' => $guest->created_at,
                        'updatedAt' => $guest->updated_at,
                    ],
                    'hasActiveSession' => $activeOrder ? true : false,
                    'accessToken' => $accessToken,
                    'refreshToken' => $refreshToken,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $guestId = $request->user()->id;
        \App\Models\Guest::query()->where('id', $guestId)->update([
            'refresh_token' => null,
            'refresh_token_expires_at' => null,
        ]);
        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $body = $request->all();
        $refreshToken = $body['refreshToken'] ?? null;
        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token là bắt buộc'], 422);
        }
        try {
            $decoded = JWT::decode($refreshToken, new Key(config('auth.refresh_token_secret'), 'HS256'));
            if (($decoded->tokenType ?? null) !== 'RefreshToken') {
                return response()->json(['message' => 'Refresh token không hợp lệ'], 401);
            }
            $guest = \App\Models\Guest::query()->find($decoded->userId);
            if (!$guest) {
                return response()->json(['message' => 'Guest không tồn tại'], 404);
            }

            $now = time();
            $accessExp = $now + $this->parseExpiry((string) config('auth.guest_access_token_expires_in', config('auth.access_token_expires_in', '1d')));
            $newAccess = [
                'userId' => $guest->id,
                'role' => \App\Models\Guest::ROLE_GUEST,
                'tokenType' => 'AccessToken',
                'iat' => $now,
                'exp' => $accessExp,
            ];
            $newAccessToken = JWT::encode($newAccess, config('auth.access_token_secret'), 'HS256');

            $newRefreshExp = $decoded->exp; // keep same exp semantics as Node
            $newRefreshPayload = [
                'userId' => $guest->id,
                'role' => \App\Models\Guest::ROLE_GUEST,
                'tokenType' => 'RefreshToken',
                'iat' => $now,
                'exp' => $newRefreshExp,
            ];
            $newRefreshToken = JWT::encode($newRefreshPayload, config('auth.refresh_token_secret'), 'HS256');

            $guest->refresh_token = $newRefreshToken;
            $guest->refresh_token_expires_at = date('Y-m-d H:i:s', $newRefreshExp);
            $guest->save();

            return response()->json([
                'message' => 'Lấy token mới thành công',
                'data' => [
                    'accessToken' => $newAccessToken,
                    'refreshToken' => $newRefreshToken,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Refresh token không hợp lệ'], 401);
        }
    }

    public function createOrders(GuestCreateOrdersRequest $request): JsonResponse
    {
        $guestId = $request->user()->id;
        $result = $this->orderService->guestCreateOrders($guestId, $request->validated());
        // Broadcasting via websockets is not implemented; return created orders
        return response()->json([
            'message' => 'Đặt món thành công',
            'data' => $result,
        ]);
    }

    public function getOrders(Request $request): JsonResponse
    {
        $guestId = $request->user()->id;
        $result = $this->orderService->guestGetOrders($guestId);
        return response()->json([
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $result,
        ]);
    }

    public function cancelOrders(Request $request): JsonResponse
    {
        $guestId = $request->user()->id;
        $result = $this->orderService->guestCancelOrders($guestId);
        return response()->json([
            'message' => 'Huỷ đơn thành công',
            'data' => $result,
        ]);
    }

    public function cancelOrderDetail($orderDetailId, Request $request): JsonResponse
    {
        $guestId = $request->user()->id;
        $result = $this->orderService->guestCancelOrderDetail($guestId, (int) $orderDetailId);
        return response()->json([
            'message' => 'Huỷ món ăn thành công',
            'data' => $result,
        ]);
    }

    public function recover(Request $request): JsonResponse
    {
        $request->validate([
            'customer_phone' => 'required|string',
            'session_pin' => 'required|string',
        ]);

        try {
            $order = \App\Models\Order::query()
                ->where('customer_phone', $request->input('customer_phone'))
                ->where('session_pin', $request->input('session_pin'))
                ->where('status', '!=', \App\Models\Order::STATUS_CANCELLED)
                ->latest()
                ->first();

            if (!$order) {
                return response()->json(['message' => 'Không tìm thấy đơn hàng nào khớp với thông tin'], 404);
            }

            // Find guest or create if not exists (for backward compatibility)
            $guest = \App\Models\Guest::query()
                ->where('order_id', $order->id)
                ->first();

            if (!$guest) {
                $guest = $this->guestRepository->create([
                    'name' => $order->customer_name,
                    'order_id' => $order->id,
                ]);
            }

            $tokens = $this->guestService->generateTokens($guest);

            return response()->json([
                'message' => 'Phục hồi phiên thành công',
                'data' => [
                    'guest' => [
                        'id' => $guest->id,
                        'name' => $guest->name,
                        'role' => \App\Models\Guest::ROLE_GUEST,
                        'orderId' => $guest->order_id,
                        'createdAt' => $guest->created_at,
                        'updatedAt' => $guest->updated_at,
                    ],
                    'accessToken' => $tokens['accessToken'],
                    'refreshToken' => $tokens['refreshToken'],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
