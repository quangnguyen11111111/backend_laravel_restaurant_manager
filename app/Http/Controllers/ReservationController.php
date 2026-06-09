<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'guest_count' => 'required|integer|min:1',
            'reservation_time' => 'required|date',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
        ]);

        try {
            $result = $this->reservationService->createReservation($request->all());
            return response()->json([
                'message' => "Tạo đặt bàn thành công",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function checkIn($orderId, Request $request): JsonResponse
    {
        $request->validate([
            'table_number' => 'required|integer'
        ]);

        try {
            $result = $this->reservationService->checkInReservation((int) $orderId, $request->input('table_number'));
            return response()->json([
                'message' => "Check-in thành công",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
