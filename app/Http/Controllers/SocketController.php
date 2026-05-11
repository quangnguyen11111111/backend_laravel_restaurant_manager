<?php

namespace App\Http\Controllers;

use App\Models\Socket;
use App\Models\Account;
use App\Models\Guest;
use App\Services\SocketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SocketController - API endpoints cho Socket Server (Node.js)
 * 
 * Chuyên xử lý request từ socket-server.js (Node.js)
 * để quản lý socket connections trong database
 */
class SocketController extends Controller
{
    protected SocketService $socketService;

    public function __construct(SocketService $socketService)
    {
        $this->socketService = $socketService;
    }

    /**
     * Upsert socket connection
     * POST /api/sockets/upsert
     * 
     * Body:
     * {
     *   "socket_id": "string",
     *   "user_id": "string|int",
     *   "role": "Owner|Manager|Staff|Guest"
     * }
     */
    public function upsert(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $socketId = $request->input('socket_id');
            $userId = $request->input('user_id');
            $role = $request->input('role');

            if (!$socketId || !$userId || !$role) {
                return response()->json(
                    ['error' => 'socket_id, user_id, role are required'],
                    422
                );
            }

            // Verify user ownership
            if ($user->id != $userId && $user->role !== 'Owner') {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $socket = $this->socketService->upsertSocket($socketId, $userId, $role);

            return response()->json([
                'success' => true,
                'data' => $socket,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove socket connection
     * POST /api/sockets/remove
     * 
     * Body:
     * {
     *   "socket_id": "string"
     * }
     */
    public function remove(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $socketId = $request->input('socket_id');
            if (!$socketId) {
                return response()->json(['error' => 'socket_id is required'], 422);
            }

            $this->socketService->removeSocket($socketId);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Find socket by user ID
     * GET /api/sockets/find/{userId}
     */
    public function find(Request $request, string $userId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Check if user is account or guest
            $socketIdAccount = $this->socketService->getSocketByAccountId($userId);
            if ($socketIdAccount) {
                return response()->json([
                    'socket_id' => $socketIdAccount,
                    'type' => 'account',
                ]);
            }

            $socketIdGuest = $this->socketService->getSocketByGuestId($userId);
            if ($socketIdGuest) {
                return response()->json([
                    'socket_id' => $socketIdGuest,
                    'type' => 'guest',
                ]);
            }

            return response()->json(['error' => 'Socket not found'], 404);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get all manager socket IDs
     * GET /api/sockets/managers
     */
    public function getManagers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Owner', 'Manager', 'Staff'])) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $socketIds = $this->socketService->getManagerSocketIds();

            return response()->json([
                'success' => true,
                'data' => $socketIds,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get socket info
     * GET /api/sockets/{socketId}
     */
    public function show(Request $request, string $socketId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $socket = Socket::where('socket_id', $socketId)->first();
            if (!$socket) {
                return response()->json(['error' => 'Socket not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $socket,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
