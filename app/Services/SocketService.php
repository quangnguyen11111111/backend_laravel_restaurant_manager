<?php

namespace App\Services;

use App\Models\Socket;
use App\Models\Account;
use App\Models\Guest;
use Illuminate\Support\Facades\Log;

/**
 * SocketService - Quản lý WebSocket connections
 * 
 * Chuyển đổi từ Node.js socket.plugins.ts
 * - Xác thực người dùng qua JWT token
 * - Lưu socketId vào database
 * - Quản lý socket rooms cho managers
 */
class SocketService
{
    /**
     * Upsert socket connection
     * 
     * @param string $socketId - Socket ID từ WebSocket server
     * @param string $userId - User ID từ JWT token
     * @param string $role - User role (Owner, Manager, Guest, etc.)
     * @return Socket
     */
    public function upsertSocket(string $socketId, string $userId, string $role): Socket
    {
        try {
            if ($role === 'Guest') {
                // Lưu socket cho Guest
                return Socket::updateOrCreate(
                    ['guest_id' => $userId],
                    ['socket_id' => $socketId]
                );
            } else {
                // Lưu socket cho Account (Owner, Manager, Staff)
                return Socket::updateOrCreate(
                    ['account_id' => $userId],
                    ['socket_id' => $socketId]
                );
            }
        } catch (\Exception $e) {
            Log::error('SocketService::upsertSocket failed', [
                'socketId' => $socketId,
                'userId' => $userId,
                'role' => $role,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Xóa socket connection khi disconnect
     * 
     * @param string $socketId
     * @return bool
     */
    public function removeSocket(string $socketId): bool
    {
        try {
            Socket::where('socket_id', $socketId)->delete();
            return true;
        } catch (\Exception $e) {
            Log::error('SocketService::removeSocket failed', [
                'socketId' => $socketId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lấy socket ID của một account
     * 
     * @param string $accountId
     * @return string|null
     */
    public function getSocketByAccountId(string $accountId): ?string
    {
        $socket = Socket::where('account_id', $accountId)->first();
        return $socket?->socket_id;
    }

    /**
     * Lấy socket ID của một guest
     * 
     * @param string $guestId
     * @return string|null
     */
    public function getSocketByGuestId(string $guestId): ?string
    {
        $socket = Socket::where('guest_id', $guestId)->first();
        return $socket?->socket_id;
    }

    /**
     * Lấy tất cả socket IDs của managers/owners (cho ManagerRoom)
     * 
     * @return array
     */
    public function getManagerSocketIds(): array
    {
        return Socket::whereHas('account', function ($query) {
            $query->whereIn('role', ['Owner', 'Manager', 'Staff']);
        })
        ->pluck('socket_id')
        ->toArray();
    }

    /**
     * Kiểm tra socket có tồn tại không
     * 
     * @param string $socketId
     * @return bool
     */
    public function socketExists(string $socketId): bool
    {
        return Socket::where('socket_id', $socketId)->exists();
    }

    /**
     * Lấy user info từ socket ID
     * 
     * @param string $socketId
     * @return array|null
     */
    public function getUserBySocket(string $socketId): ?array
    {
        $socket = Socket::where('socket_id', $socketId)->first();

        if (!$socket) {
            return null;
        }

        if ($socket->guest_id) {
            return [
                'type' => 'guest',
                'id' => $socket->guest_id,
                'user' => $socket->guest,
            ];
        }

        if ($socket->account_id) {
            return [
                'type' => 'account',
                'id' => $socket->account_id,
                'user' => $socket->account,
            ];
        }

        return null;
    }
}
