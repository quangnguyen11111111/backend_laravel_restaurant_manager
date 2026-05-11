<?php

namespace App\Http\Controllers;

use App\Http\Requests\Request;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(HttpRequest $request): JsonResponse
    {
        $payload = $request->all();
        try {
            $result = $this->orderService->createOrders($request->user()->id, $payload);
            return response()->json([
                'message' => "Tạo thành công " . count($result) . " đơn hàng cho khách hàng",
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function index(HttpRequest $request): JsonResponse
    {
        $from = $request->query('fromDate');
        $to = $request->query('toDate');
        $result = $this->orderService->getOrders($from, $to);
        return response()->json([
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $result,
        ]);
    }

    public function show($orderId): JsonResponse
    {
        $result = $this->orderService->getOrderDetail((int) $orderId);
        return response()->json([
            'message' => 'Lấy đơn hàng thành công',
            'data' => $result,
        ]);
    }

    public function update($orderId, HttpRequest $request): JsonResponse
    {
        $body = $request->all();
        $body['orderHandlerId'] = $request->user()->id;
        $result = $this->orderService->updateOrder((int) $orderId, $body);
        return response()->json([
            'message' => 'Cập nhật đơn hàng thành công',
            'data' => $result,
        ]);
    }

    public function pay(HttpRequest $request): JsonResponse
    {
        $guestId = $request->input('guestId');
        $result = $this->orderService->payOrders($guestId, $request->user()->id);
        return response()->json([
            'message' => "Thanh toán thành công " . count($result) . " đơn",
            'data' => $result,
        ]);
    }
}
