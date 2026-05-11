<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Dish;
use App\Models\DishSnapshot;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct() {}

    public function createOrders(int $orderHandlerId, array $body): array
    {
        $guestId = $body['guestId'];
        $orders = $body['orders'];

        $guest = Guest::query()->find($guestId);
        if (!$guest) {
            throw new ServiceException('Guest không tồn tại', 400);
        }
        if ($guest->table_number === null) {
            throw new ServiceException('Bàn gắn liền với khách hàng này đã bị xóa, vui lòng chọn khách hàng khác!', 400);
        }

        $table = Table::query()->where('number', $guest->table_number)->first();
        if (!$table) {
            throw new ServiceException('Bàn không tồn tại', 400);
        }
        if ($table->status === Table::STATUS_HIDDEN) {
            throw new ServiceException("Bàn {$table->number} gắn liền với khách hàng đã bị ẩn, vui lòng chọn khách hàng khác!", 400);
        }

        $created = DB::transaction(function () use ($orders, $guestId, $orderHandlerId, $guest) {
            $records = [];
            foreach ($orders as $order) {
                $dish = Dish::query()->find($order['dishId']);
                if (!$dish) {
                    throw new ServiceException('Món không tồn tại', 400);
                }
                if ($dish->status === Dish::STATUS_UNAVAILABLE) {
                    throw new ServiceException("Món {$dish->name} đã hết", 400);
                }
                if ($dish->status === Dish::STATUS_HIDDEN) {
                    throw new ServiceException("Món {$dish->name} không thể đặt", 400);
                }
                $dishSnapshot = DishSnapshot::query()->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $orderRecord = Order::query()->create([
                    'dish_snapshot_id' => $dishSnapshot->id,
                    'guest_id' => $guestId,
                    'quantity' => $order['quantity'],
                    'table_number' => $guest->table_number,
                    'order_handler_id' => $orderHandlerId,
                    'status' => Order::STATUS_PENDING,
                ]);
                $records[] = $orderRecord->load(['dishSnapshot', 'guest']);
            }
            return $records;
        });

        // socket logic not implemented; return orders
        return $created;
    }

    public function guestCreateOrders(int $guestId, array $orders): array
    {
        $created = DB::transaction(function () use ($orders, $guestId) {
            $guest = Guest::query()->find($guestId);
            if (!$guest) {
                throw new ServiceException('Guest không tồn tại', 400);
            }
            if ($guest->table_number === null) {
                throw new ServiceException('Bàn của bạn đã bị xóa, vui lòng đăng xuất và đăng nhập lại một bàn mới', 400);
            }
            $table = Table::query()->where('number', $guest->table_number)->first();
            if (!$table) {
                throw new ServiceException('Bàn không tồn tại', 400);
            }
            if ($table->status === Table::STATUS_HIDDEN) {
                throw new ServiceException("Bàn {$table->number} đã bị ẩn, vui lòng đăng xuất và chọn bàn khác", 400);
            }
            if ($table->status === Table::STATUS_RESERVED) {
                throw new ServiceException("Bàn {$table->number} đã được đặt trước, vui lòng đăng xuất và chọn bàn khác", 400);
            }
            $records = [];
            foreach ($orders as $order) {
                $dish = Dish::query()->find($order['dishId']);
                if (!$dish) {
                    throw new ServiceException('Món không tồn tại', 400);
                }
                if ($dish->status === Dish::STATUS_UNAVAILABLE) {
                    throw new ServiceException("Món {$dish->name} đã hết", 400);
                }
                if ($dish->status === Dish::STATUS_HIDDEN) {
                    throw new ServiceException("Món {$dish->name} không thể đặt", 400);
                }
                $dishSnapshot = DishSnapshot::query()->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $orderRecord = Order::query()->create([
                    'dish_snapshot_id' => $dishSnapshot->id,
                    'guest_id' => $guestId,
                    'quantity' => $order['quantity'],
                    'table_number' => $guest->table_number,
                    'order_handler_id' => null,
                    'status' => Order::STATUS_PENDING,
                ]);
                $records[] = $orderRecord->load(['dishSnapshot', 'guest']);
            }
            return $records;
        });

        return $created;
    }

    public function guestGetOrders(int $guestId): array
    {
        $orders = Order::query()->where('guest_id', $guestId)->with(['dishSnapshot', 'orderHandler', 'guest'])->orderBy('created_at', 'desc')->get();
        return $orders->toArray();
    }

    public function getOrders(?string $fromDate = null, ?string $toDate = null)
    {
        $query = Order::query()->with(['dishSnapshot', 'orderHandler', 'guest'])->orderBy('created_at', 'desc');
        if (!empty($fromDate)) {
            $query->where('created_at', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $query->where('created_at', '<=', $toDate);
        }
        return $query->get()->toArray();
    }

    public function getOrderDetail(int $orderId)
    {
        return Order::query()->with(['dishSnapshot', 'orderHandler', 'guest', 'table'])->findOrFail($orderId);
    }

    public function updateOrder(int $orderId, array $body)
    {
        return DB::transaction(function () use ($orderId, $body) {
            $order = Order::query()->with('dishSnapshot')->findOrFail($orderId);
            $dishSnapshotId = $order->dish_snapshot_id;
            if (array_key_exists('dishId', $body) && $order->dishSnapshot->dish_id !== $body['dishId']) {
                $dish = Dish::query()->findOrFail($body['dishId']);
                $dishSnapshot = DishSnapshot::query()->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $dishSnapshotId = $dishSnapshot->id;
            }
            $order->status = $body['status'];
            $order->dish_snapshot_id = $dishSnapshotId;
            $order->quantity = $body['quantity'];
            $order->order_handler_id = $body['orderHandlerId'];
            $order->save();
            return $order->load(['dishSnapshot', 'orderHandler', 'guest']);
        });
    }

    public function payOrders(int $guestId, int $orderHandlerId)
    {
        $orders = Order::query()->where('guest_id', $guestId)->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_DELIVERED])->get();
        if ($orders->isEmpty()) {
            throw new ServiceException('Không có hóa đơn nào cần thanh toán', 400);
        }
        DB::transaction(function () use ($orders, $orderHandlerId) {
            $ids = $orders->pluck('id')->toArray();
            Order::query()->whereIn('id', $ids)->update([
                'status' => Order::STATUS_PAID,
                'order_handler_id' => $orderHandlerId,
            ]);
        });
        return Order::query()->whereIn('id', $orders->pluck('id')->toArray())->with(['dishSnapshot', 'orderHandler', 'guest'])->orderBy('created_at', 'desc')->get()->toArray();
    }
}
