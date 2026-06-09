<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TableCapacityController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function checkCapacity(Request $request): JsonResponse
    {
        $request->validate([
            'guest_count' => 'required|integer|min:1',
            'target_time' => 'required|date',
        ]);

        try {
            $result = $this->reservationService->getCapacity(
                $request->input('guest_count'),
                $request->input('target_time')
            );
            return response()->json([
                'message' => "Kiểm tra sức chứa thành công",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
