<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    /**
     * Lấy danh sách các món đang chờ chế biến, gom nhóm theo dish_id.
     */
    public function getConsolidatedOrders()
    {
        $orderDetails = OrderDetail::with(['order:id,table_number,created_at', 'dish'])
            ->whereIn('status', [OrderDetail::STATUS_PENDING, OrderDetail::STATUS_PROCESSING])
            ->get();

        $grouped = $orderDetails->groupBy('dish_id');

        $result = [];

        foreach ($grouped as $dishId => $details) {
            $dishName = $details->first()->dish_name ?? ($details->first()->dish->name ?? 'Unknown');
            $dishImage = $details->first()->dish_image ?? ($details->first()->dish->image ?? '');
            $totalQuantity = $details->sum('quantity');
            $pendingQuantity = $details->where('status', OrderDetail::STATUS_PENDING)->sum('quantity');
            $processingQuantity = $details->where('status', OrderDetail::STATUS_PROCESSING)->sum('quantity');

            // Sắp xếp các order detail theo thời gian order (created_at của order)
            $waitingList = $details->map(function ($detail) {
                return [
                    'order_detail_id' => $detail->id,
                    'table_number' => $detail->order->table_number ?? 'Chuẩn bị món trước',
                    'quantity' => $detail->quantity,
                    'ordered_at' => $detail->order->created_at,
                    'note' => $detail->note,
                    'status' => $detail->status,
                ];
            })->sortBy('ordered_at')->values()->all();

            $result[] = [
                'dish_id' => $dishId,
                'dish_name' => $dishName,
                'dish_image' => $dishImage,
                'total_quantity' => $totalQuantity,
                'pending_quantity' => $pendingQuantity,
                'processing_quantity' => $processingQuantity,
                'waiting_list' => $waitingList,
            ];
        }

        return response()->json([
            'message' => 'Lấy danh sách thành công',
            'data' => $result
        ]);
    }

    /**
     * Đánh dấu món đã chế biến xong (Chuyển thành Delivered).
     */
    public function markAsDone($orderDetailId)
    {
        try {
            DB::beginTransaction();

            $orderDetail = OrderDetail::findOrFail($orderDetailId);
            
            // Theo yêu cầu mới, trạng thái chuyển thẳng thành Delivered (Đã giao)
            $orderDetail->state()->transitionTo(OrderDetail::STATUS_DELIVERED, $orderDetail);
            $orderDetail->save();

            DB::commit();

            return response()->json([
                'message' => 'Đã đánh dấu hoàn thành',
                'data' => $orderDetail
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi cập nhật trạng thái',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Đánh dấu món đang được chế biến.
     */
    public function markAsProcessing($orderDetailId)
    {
        try {
            DB::beginTransaction();

            $orderDetail = OrderDetail::findOrFail($orderDetailId);
            
            $orderDetail->state()->transitionTo(OrderDetail::STATUS_PROCESSING, $orderDetail);
            $orderDetail->save();

            DB::commit();

            return response()->json([
                'message' => 'Đã đánh dấu đang nấu',
                'data' => $orderDetail
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi cập nhật trạng thái',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Đánh dấu tất cả các suất ăn (Pending) của 1 món đang được chế biến.
     */
    public function markAllAsProcessing($dishId)
    {
        try {
            DB::beginTransaction();

            $orderDetails = OrderDetail::where('dish_id', $dishId)
                ->where('status', OrderDetail::STATUS_PENDING)
                ->get();
            
            $updatedCount = 0;
            foreach ($orderDetails as $orderDetail) {
                $orderDetail->state()->transitionTo(OrderDetail::STATUS_PROCESSING, $orderDetail);
                $orderDetail->save();
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Đã đánh dấu đang nấu cho $updatedCount suất ăn",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi cập nhật trạng thái',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

