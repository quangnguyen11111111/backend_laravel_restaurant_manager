# Hướng Dẫn Khắc Phục Lỗi CORS Socket.IO

## 🔴 Vấn Đề Gốc

Frontend gặp lỗi:

```
Access to XMLHttpRequest at 'http://localhost:8000/socket.io/?EIO=4&transport=polling&t=p87sfv46'
from origin 'http://localhost:3000' has been blocked by CORS policy:
No 'Access-Control-Allow-Origin' header is present on the requested resource.

utils.ts:217  GET http://localhost:8000/socket.io/?EIO=4&transport=polling&t=p87sfv46 net::ERR_FAILED 404 (Not Found)
```

**Nguyên nhân chính:**

- Frontend đang cố kết nối tới `http://localhost:8000` (port của Laravel)
- Nhưng Socket.IO server chạy trên port **3001** (riêng biệt)
- CORS chưa được cấu hình đúng để cho phép kết nối từ frontend

## ✅ Giải Pháp Áp Dụng

### 1️⃣ Backend - Cấu Hình Socket Server (Đã Hoàn Thiện)

**File: `socket-server.js`**

```javascript
const io = new Server(PORT, {
    cors: {
        origin: FRONTEND_URL, // http://localhost:3000
        methods: ["GET", "POST"],
        credentials: true,
        allowEIO3: true,
    },
    transports: ["websocket", "polling"],
    pingInterval: 10000,
    pingTimeout: 5000,
});
```

**Thay đổi từ NextJs-Super-BackEnd-main:**

- ✅ Cấu hình CORS với `origin`, `methods`, `credentials`
- ✅ Thêm `allowEIO3: true` để tương thích với cách client kết nối
- ✅ Cấu hình `transports` bao gồm cả `websocket` và `polling`
- ✅ Thêm `pingInterval` và `pingTimeout` để giữ kết nối sống

### 2️⃣ Frontend - Cấu Hình Kết Nối (Đã Hoàn Thiện)

**File: `.env`**

```env
NEXT_PUBLIC_API_ENDPOINT=http://localhost:8000
NEXT_PUBLIC_SOCKET_ENDPOINT=http://localhost:3001
NEXT_PUBLIC_URL=http://localhost:3000
```

**File: `config.ts`**

```typescript
const configSchema = z.object({
    NEXT_PUBLIC_API_ENDPOINT: z.string(),
    NEXT_PUBLIC_SOCKET_ENDPOINT: z.string(), // ← Thêm mới
    NEXT_PUBLIC_URL: z.string(),
});
```

**File: `lib/utils.ts`**

```typescript
export const generateSocketInstace = (accessToken: string) => {
    return io(envConfig.NEXT_PUBLIC_SOCKET_ENDPOINT, {
        // ← Thay từ NEXT_PUBLIC_API_ENDPOINT
        auth: {
            Authorization: `Bearer ${accessToken}`,
        },
    });
};
```

### 3️⃣ Backend - Thêm Socket API Routes (Đã Hoàn Thiện)

**File: `routes/api.php`**

```php
// Socket Routes (cho Socket Server Node.js)
Route::prefix('sockets')->middleware('jwt.auth')->group(function () {
    Route::post('/upsert', [SocketController::class, 'upsert']);
    Route::post('/remove', [SocketController::class, 'remove']);
    Route::get('/find/{userId}', [SocketController::class, 'find']);
    Route::get('/managers', [SocketController::class, 'getManagers']);
});
```

### 4️⃣ Backend - Package Dependencies (Đã Hoàn Thiện)

**File: `package.json`**

```json
{
    "dependencies": {
        "socket.io": "^4.8.1",
        "axios": "^1.7.8",
        "dotenv": "^16.4.7"
    },
    "scripts": {
        "socket": "node socket-server.js",
        "dev:socket": "concurrently \"npm run dev\" \"npm run socket\""
    }
}
```

## 🚀 Hướng Dẫn Chạy

### 1. Cài đặt Dependencies

```bash
# Backend Laravel
cd backend_laravel_restaurant_manager
npm install

# Frontend Next.js
cd ../frontend_nextjs_restaurant_manager
npm install
```

### 2. Cấu Hình Environment

**Backend: `.env` hoặc sao chép từ `.env.socket`**

```env
SOCKET_PORT=3001
LARAVEL_API_URL=http://localhost:8000/api
SOCKET_SERVER_URL=http://localhost:3001
FRONTEND_URL=http://localhost:3000
NODE_ENV=development
```

**Frontend: `.env` (đã cập nhật)**

```env
NEXT_PUBLIC_API_ENDPOINT=http://localhost:8000
NEXT_PUBLIC_SOCKET_ENDPOINT=http://localhost:3001
NEXT_PUBLIC_URL=http://localhost:3000
```

### 3. Chạy Backend Laravel

```bash
cd backend_laravel_restaurant_manager
php artisan migrate
php artisan serve
```

Server Laravel sẽ chạy trên: `http://localhost:8000`

### 4. Chạy Socket Server (Terminal mới)

```bash
cd backend_laravel_restaurant_manager
node socket-server.js
```

Hoặc chạy kèm với Vite:

```bash
npm run dev:socket
```

Socket server sẽ chạy trên: `http://localhost:3001` với CORS hỗ trợ `http://localhost:3000`

### 5. Chạy Frontend Next.js (Terminal mới)

```bash
cd frontend_nextjs_restaurant_manager
npm run dev
```

Frontend sẽ chạy trên: `http://localhost:3000`

## 🧪 Kiểm Tra Kết Nối

### 1. Trong Browser Console

```javascript
// Khi đã đăng nhập, tại các trang cần Socket
console.log("Socket connected:", socket.connected);
console.log("Socket ID:", socket.id);
```

### 2. Xem Logs

**Frontend (Browser):**

- Mở DevTools (F12) → Console
- Không nên thấy lỗi CORS nữa
- Kiểm tra Network tab → WebSocket connections

**Backend (Node.js):**

```
🚀 Socket server running on port 3001
📡 Connected to Laravel API: http://localhost:8000/api
🔌 Socket connected: socket_id_here - User: 1
```

### 3. Test Endpoint

```bash
# Verify token (cần valid JWT token)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/user

# Check socket routes
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/sockets/managers
```

## 📋 Checklist

- [ ] Cập nhật `socket-server.js` với CORS config
- [ ] Thêm dependencies vào `package.json`
- [ ] Thêm `NEXT_PUBLIC_SOCKET_ENDPOINT` vào `.env` frontend
- [ ] Cập nhật `config.ts` frontend
- [ ] Cập nhật `generateSocketInstace` trong `lib/utils.ts`
- [ ] Thêm socket routes vào `routes/api.php`
- [ ] Chạy `npm install` ở backend
- [ ] Chạy Laravel migration (nếu chưa)
- [ ] Chạy `php artisan serve`
- [ ] Chạy `node socket-server.js` (port 3001)
- [ ] Chạy `npm run dev` ở frontend (port 3000)
- [ ] Kiểm tra browser console không có CORS error
- [ ] Kiểm tra Socket server logs có "connected" message

## 🔧 Troubleshooting

### ❌ Vẫn thấy lỗi CORS 404

**Nguyên nhân:**

- Socket server chưa được khởi chạy (port 3001)
- Frontend vẫn đang kết nối tới port 8000

**Giải pháp:**

1. Kiểm tra `node socket-server.js` đang chạy:

    ```bash
    lsof -i :3001  # macOS/Linux
    netstat -ano | findstr :3001  # Windows
    ```

2. Kiểm tra `.env` frontend có `NEXT_PUBLIC_SOCKET_ENDPOINT=http://localhost:3001`

3. Kiểm tra `generateSocketInstace` sử dụng `NEXT_PUBLIC_SOCKET_ENDPOINT`

### ❌ Lỗi "Token không hợp lệ"

**Nguyên nhân:**

- JWT token chưa được set trong localStorage
- Token đã hết hạn

**Giải pháp:**

1. Đăng nhập lại frontend
2. Kiểm tra `localStorage.getItem('accessToken')`
3. Verify token từ API: `GET /api/user` (thêm header `Authorization: Bearer TOKEN`)

### ❌ Socket disconnect liên tục

**Nguyên nhân:**

- Laravel API URL không đúng
- Token verification thất bại
- Network timeout

**Giải pháp:**

1. Kiểm tra `LARAVEL_API_URL` trong `.env.socket`
2. Kiểm tra Laravel server đang chạy: `php artisan serve`
3. Kiểm tra logs: `php artisan tinker` → `echo \App\Models\Account::first()`

## 📚 Tài Liệu Tham Khảo

- NextJs-Super-BackEnd-main: Socket.IO configuration chuẩn
- Socket.IO Docs: https://socket.io/docs/
- Laravel API: https://laravel.com/docs/11/eloquent

## ✨ Kết Quả

Sau khi áp dụng:

- ✅ Frontend kết nối tới đúng Socket server (port 3001)
- ✅ CORS headers được set đúng (Allow-Origin: http://localhost:3000)
- ✅ Không còn lỗi 404 Not Found
- ✅ Socket connection ổn định
- ✅ Real-time communication hoạt động
