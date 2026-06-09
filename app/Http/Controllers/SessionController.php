<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Models\Guest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SessionController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function hostOpen(Request $request): JsonResponse
    {
        $request->validate([
            'table_number' => 'required|integer',
            'guest_count' => 'nullable|integer|min:1',
        ]);

        try {
            // Assume guest is authenticated via some guest middleware
            $guestId = $request->user()->id; 
            
            $result = $this->orderService->openTableSession(
                $request->input('table_number'), 
                $guestId,
                $request->input('guest_count', 1)
            );
            return response()->json([
                'message' => "Mở bàn thành công",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function guestJoin(Request $request): JsonResponse
    {
        $request->validate([
            'table_number' => 'required|integer',
            'session_pin' => 'required|string',
        ]);

        try {
            $tableNumber = $request->input('table_number');
            $pin = $request->input('session_pin');

            $order = $this->orderService->getActiveOrderForTable($tableNumber);
            if (!$order) {
                throw new ServiceException('Bàn chưa được mở', 400);
            }

            if (strtoupper($order->session_pin) !== strtoupper($pin)) {
                throw new ServiceException('Mã PIN không chính xác', 400);
            }

            $guestId = $request->user()->id;
            $guest = Guest::find($guestId);
            if ($guest) {
                $guest->update(['order_id' => $order->id]);
            }

            return response()->json([
                'message' => "Join bàn thành công",
                'data' => $order,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
