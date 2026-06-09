/**
 * Socket Server cho Restaurant Manager Backend
 *
 * Chuyển đổi từ NextJs-Super-BackEnd-main/src/plugins/socket.plugins.ts
 * Giữ nguyên chức năng Node.js + Socket.io nhưng kết nối với Laravel backend
 *
 * Cách chạy:
 * npm install socket.io axios dotenv
 * node socket-server.js
 *
 * Hoặc chạy cùng lúc với npm dev:
 * npm run dev (sẽ chạy concurrently: vite + socket-server.js)
 */

import { Server } from "socket.io";
import axios from "axios";
import dotenv from "dotenv";

dotenv.config();

const PORT = process.env.SOCKET_PORT || 3001;
const LARAVEL_API_URL =
    process.env.LARAVEL_API_URL || "http://localhost:8000/api";
const FRONTEND_URL = process.env.FRONTEND_URL || "http://localhost:3000";
const MANAGER_ROOM = "manager-room";

// Khởi tạo Socket.io server với cấu hình CORS đúng
const io = new Server(PORT, {
    cors: {
        origin: FRONTEND_URL,
        methods: ["GET", "POST"],
        credentials: true,
        allowEIO3: true,
    },
    transports: ["websocket", "polling"],
    pingInterval: 10000,
    pingTimeout: 5000,
    maxHttpBufferSize: 1e6,
});

/**
 * Verify JWT token từ Laravel backend
 */
async function verifyTokenWithLaravel(token) {
    try {
        const response = await axios.get(`${LARAVEL_API_URL}/user`, {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        });
        return response.data; // Trả về user data từ Laravel
    } catch (error) {
        console.error("Token verification failed:", error.message);
        return null;
    }
}

/**
 * Upsert socket connection vào Laravel database
 */
async function upsertSocketInLaravel(socketId, userId, role, token) {
    try {
        const response = await axios.post(
            `${LARAVEL_API_URL}/sockets/upsert`,
            {
                socket_id: socketId,
                user_id: userId,
                role: role,
            },
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            },
        );
        return response.data;
    } catch (error) {
        console.error("Upsert socket failed:", error.message);
        return null;
    }
}

/**
 * Remove socket connection từ Laravel database
 */
async function removeSocketFromLaravel(socketId, token) {
    try {
        await axios.post(
            `${LARAVEL_API_URL}/sockets/remove`,
            {
                socket_id: socketId,
            },
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            },
        );
    } catch (error) {
        console.error("Remove socket failed:", error.message);
    }
}

/**
 * Middleware: Xác thực socket connection
 */
io.use(async (socket, next) => {
    try {
        const { Authorization } = socket.handshake.auth;

        if (!Authorization) {
            return next(new Error("Authorization header không hợp lệ"));
        }

        const token = Authorization.split(" ")[1];
        if (!token) {
            return next(new Error("Token không hợp lệ"));
        }

        // Verify token với Laravel backend
        const userData = await verifyTokenWithLaravel(token);
        if (!userData) {
            return next(new Error("Token không hợp lệ hoặc hết hạn"));
        }

        // Lưu decoded token vào socket data
        socket.handshake.auth.decodedAccessToken = userData;
        socket.handshake.auth.token = token;

        next();
    } catch (error) {
        next(error);
    }
});

/**
 * Connection handler
 */
io.on("connection", async (socket) => {
    try {
        const { decodedAccessToken, token } = socket.handshake.auth;
        const { id: userId, role } = decodedAccessToken;

        console.log(`🔌 Socket connected: ${socket.id} - User: ${userId}`);

        // Upsert socket vào Laravel database
        await upsertSocketInLaravel(socket.id, userId, role, token);

        // Nếu là manager/owner, join vào ManagerRoom
        if (["Owner", "Manager", "Staff"].includes(role)) {
            socket.join(MANAGER_ROOM);
            console.log(`📢 User ${userId} joined ManagerRoom`);

            // Broadcast event UserConnected
            io.to(MANAGER_ROOM).emit("user:connected", {
                userId,
                role,
                socketId: socket.id,
                timestamp: new Date(),
            });
        }

        /**
         * Xử lý disconnect
         */
        socket.on("disconnect", async (reason) => {
            console.log(
                `🔌 Socket disconnected: ${socket.id} - Reason: ${reason}`,
            );

            // Remove socket từ Laravel database
            await removeSocketFromLaravel(socket.id, token);

            // Broadcast event UserDisconnected
            if (["Owner", "Manager", "Staff"].includes(role)) {
                io.to(MANAGER_ROOM).emit("user:disconnected", {
                    userId,
                    role,
                    socketId: socket.id,
                    timestamp: new Date(),
                });
            }
        });

        /**
         * Event: Gửi notification tới user
         * Client gọi: socket.emit('send:notification', { targetUserId, message, ... })
         */
        socket.on("send:notification", async (data) => {
            try {
                // Tìm socket ID của target user từ Laravel
                const response = await axios.get(
                    `${LARAVEL_API_URL}/sockets/find/${data.targetUserId}`,
                    {
                        headers: {
                            Authorization: `Bearer ${token}`,
                        },
                    },
                );

                if (response.data.socket_id) {
                    io.to(response.data.socket_id).emit(
                        "receive:notification",
                        {
                            from: userId,
                            ...data,
                            timestamp: new Date(),
                        },
                    );
                }
            } catch (error) {
                console.error("Send notification failed:", error.message);
            }
        });

        /**
         * Event: Broadcast tới tất cả managers
         * Client gọi: socket.emit('broadcast:managers', { message, ... })
         */
        socket.on("broadcast:managers", (data) => {
            if (["Owner", "Manager", "Staff"].includes(role)) {
                io.to(MANAGER_ROOM).emit("receive:broadcast", {
                    from: userId,
                    ...data,
                    timestamp: new Date(),
                });
            }
        });
        /**
         * Event: Cập nhật đơn hàng (gửi tới khách)
         * Client gọi: socket.emit('update-order', { guestId, data })
         */
        socket.on("update-order", async ({ guestId, data }) => {
            try {
                const response = await axios.get(
                    `${LARAVEL_API_URL}/sockets/find/${guestId}`,
                    { headers: { Authorization: `Bearer ${token}` } }
                );
                if (response.data.socket_id) {
                    io.to(response.data.socket_id).emit("update-order", data);
                }
                // Broadcast cho tất cả quản lý để cập nhật giao diện
                io.to(MANAGER_ROOM).emit("update-order", data);
            } catch (error) {
                console.error("Update order broadcast failed:", error.message);
            }
        });

        /**
         * Event: Thanh toán (gửi tới khách)
         * Client gọi: socket.emit('payment', { guestId, data })
         */
        socket.on("payment", async ({ guestId, data }) => {
            try {
                const response = await axios.get(
                    `${LARAVEL_API_URL}/sockets/find/${guestId}`,
                    { headers: { Authorization: `Bearer ${token}` } }
                );
                if (response.data.socket_id) {
                    io.to(response.data.socket_id).emit("payment", data);
                }
                io.to(MANAGER_ROOM).emit("payment", data);
            } catch (error) {
                console.error("Payment broadcast failed:", error.message);
            }
        });

        /**
         * Event: Khách hàng đặt món mới
         * Client gọi: socket.emit('new-order', data)
         */
        socket.on("new-order", (data) => {
            io.to(MANAGER_ROOM).emit("new-order", data);
        });
    } catch (error) {
        console.error("Connection error:", error.message);
    }
});

/**
 * Health check endpoint
 */
io.on("ping", (callback) => {
    callback();
});

console.log(`🚀 Socket server running on port ${PORT}`);
console.log(`📡 Connected to Laravel API: ${LARAVEL_API_URL}`);
