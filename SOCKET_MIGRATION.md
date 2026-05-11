# Socket Server Migration - Node.js sang Laravel

## 📋 Tổng Quan

Dự án chuyển đổi logic WebSocket từ NextJs-Super-BackEnd-main (Node.js + Fastify) sang backend_laravel_restaurant_manager với hỗ trợ real-time communication.

### File gốc được chuyển đổi:
- **Nguồn**: `NextJs-Super-BackEnd-main/src/plugins/socket.plugins.ts` (Node.js + Fastify)
- **Đích**: `backend_laravel_restaurant_manager/`

---

## 🏗️ Kiến Trúc

### Cấu Trúc Hoàn Toàn
```
backend_laravel_restaurant_manager/
├── socket-server.js                              # Node.js WebSocket Server
├── app/
│   ├── Models/
│   │   └── Socket.php                           # Socket Model (lưu socket connections)
│   ├── Services/
│   │   └── SocketService.php                    # Business logic cho socket
│   ├── Http/
│   │   └── Controllers/
│   │       └── SocketController.php             # API endpoints
│   └── Events/
│       ├── UserConnected.php                    # Event khi user connect
│       └── UserDisconnected.php                 # Event khi user disconnect
├── database/
│   └── migrations/
│       └── 2026_05_09_000001_create_sockets_table.php
├── routes/
│   └── api.php                                  # API routes (có socket routes)
├── .env.example                                 # Environment config (có socket settings)
└── package.json                                 # Node.js dependencies (có socket.io)
```

### Phương Án Kiến Trúc
Sử dụng **2-tier architecture**:
1. **Node.js WebSocket Server** (socket-server.js)
   - Xử lý WebSocket connections
   - Manage socket rooms
   - Broadcast events
   
2. **Laravel Backend** 
   - Xác thực JWT tokens
   - Lưu/quản lý socket records
   - Cung cấp API cho socket server

---

## 📊 So Sánh Node.js vs Laravel Implementation

### Node.js (Socket.plugins.ts)
```javascript
// Middleware xác thực
fastify.io.use(async (socket, next) => {
  const accessToken = Authorization.split(' ')[1]
  const decodedAccessToken = verifyAccessToken(accessToken)
  
  // Lưu socket
  await prisma.socket.upsert({
    where: { guestId: userId },
    create: { guestId: userId, socketId: socket.id }
  })
})
```

### Laravel (SocketService.php)
```php
public function upsertSocket(string $socketId, string $userId, string $role): Socket
{
    return Socket::updateOrCreate(
        ['guest_id' => $userId],
        ['socket_id' => $socketId]
    );
}
```

---

## 🔧 Setup & Cài Đặt

### 1. Chạy Migration
```bash
php artisan migrate
```

Điều này sẽ tạo bảng `sockets` với cấu trúc:
```
- id (primary key)
- socket_id (unique)
- account_id (nullable, foreign key)
- guest_id (nullable, foreign key)
- timestamps
```

### 2. Cài Dependencies Node.js
```bash
npm install
```

Điều này cài:
- `socket.io` - WebSocket library
- `axios` - HTTP client (gọi Laravel API)
- `cors` - Cross-origin support
- `dotenv` - Environment variables

### 3. Cấu Hình Environment

Thêm vào `.env`:
```env
# Socket Server
SOCKET_PORT=3001
LARAVEL_API_URL=http://localhost:8000/api
SOCKET_SERVER_URL=http://localhost:3001

# Frontend URL (để CORS)
FRONTEND_URL=http://localhost:3000
```

### 4. Chạy Socket Server
```bash
npm run socket:dev
```

### 5. Chạy Laravel Backend (Terminal khác)
```bash
php artisan serve
```

---

## 📡 Luồng Kết Nối

### 1. Client Kết Nối (Frontend)
```javascript
// Frontend (Next.js)
import { io } from 'socket.io-client'

const socket = io('http://localhost:3001', {
  auth: {
    Authorization: `Bearer ${accessToken}`
  }
})
```

### 2. Server Xác Thực
```
1. Client gửi Authorization header
2. Socket Server (Node.js) nhận request
3. Verify JWT token với Laravel (`GET /api/user`)
4. Lưu socket connection (`POST /api/sockets/upsert`)
5. Nếu là Manager/Owner, join ManagerRoom
6. Emit 'user:connected' event
```

### 3. Client Gửi Data
```javascript
// Gửi notification
socket.emit('send:notification', {
  targetUserId: 123,
  message: 'Hello'
})
```

### 4. Server Xử Lý
```
1. Socket Server nhận event
2. Tìm target socket ID (`GET /api/sockets/find/{userId}`)
3. Gửi tới target socket qua `io.to(socketId).emit()`
4. Event phát tới client: 'receive:notification'
```

---

## 📚 API Endpoints

### Socket API Routes (Laravel)

#### 1. Upsert Socket Connection
```
POST /api/sockets/upsert
Authorization: Bearer {token}

Body:
{
  "socket_id": "eJ7c9Uo_AAAB",
  "user_id": 1,
  "role": "Owner|Manager|Guest"
}

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "socket_id": "eJ7c9Uo_AAAB",
    "account_id": 1,
    "guest_id": null,
    "created_at": "2026-05-09T...",
    "updated_at": "2026-05-09T..."
  }
}
```

#### 2. Remove Socket Connection
```
POST /api/sockets/remove
Authorization: Bearer {token}

Body:
{
  "socket_id": "eJ7c9Uo_AAAB"
}

Response:
{
  "success": true
}
```

#### 3. Find Socket by User ID
```
GET /api/sockets/find/{userId}
Authorization: Bearer {token}

Response:
{
  "socket_id": "eJ7c9Uo_AAAB",
  "type": "account"
}
```

#### 4. Get All Manager Socket IDs
```
GET /api/sockets/managers
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": [
    "eJ7c9Uo_AAAB",
    "kL9d2Po_CCCD",
    ...
  ]
}
```

#### 5. Get Socket Info
```
GET /api/sockets/{socketId}
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": { ... }
}
```

---

## 🎯 Socket Events

### Client → Server Events

#### 1. send:notification
Gửi notification tới một user cụ thể
```javascript
socket.emit('send:notification', {
  targetUserId: 123,
  message: 'Đơn hàng đã sẵn sàng',
  type: 'order'
})
```

#### 2. broadcast:managers
Broadcast tới tất cả managers/owners
```javascript
socket.emit('broadcast:managers', {
  message: 'Có đơn hàng mới',
  orderId: 456
})
```

### Server → Client Events

#### 1. user:connected
Phát khi user kết nối (chỉ cho managers)
```javascript
socket.on('user:connected', (data) => {
  console.log(`User ${data.userId} connected`)
})
```

#### 2. user:disconnected
Phát khi user ngắt kết nối
```javascript
socket.on('user:disconnected', (data) => {
  console.log(`User ${data.userId} disconnected`)
})
```

#### 3. receive:notification
Phát khi nhận notification
```javascript
socket.on('receive:notification', (data) => {
  console.log(`Từ ${data.from}: ${data.message}`)
})
```

#### 4. receive:broadcast
Broadcast tới tất cả managers
```javascript
socket.on('receive:broadcast', (data) => {
  console.log(`Broadcast từ ${data.from}`)
})
```

---

## 🔐 Security & Authentication

### JWT Token Verification
```
1. Frontend gửi Authorization header: "Bearer {token}"
2. Socket Server gọi: GET /api/user (qua header này)
3. Laravel Sanctum verify token
4. Return user data nếu hợp lệ
5. Socket lưu user info vào socket.handshake.auth
```

### Authorization Checks
- **Public Events**: Bất kỳ user đã authentication
- **Manager Events**: Chỉ Owner, Manager, Staff
- **User-Specific**: Chỉ user đó hoặc owner

---

## 🚀 Deployment

### Development
```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Socket Server
npm run socket:dev
```

### Production
```bash
# Build frontend
npm run build

# Start socket server
npm run socket:prod

# Start Laravel
php artisan serve --host=0.0.0.0 --port=8000
```

### Docker (Optional)
```dockerfile
# Dockerfile cho Socket Server
FROM node:20
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
CMD ["npm", "run", "socket:prod"]
```

---

## 🐛 Troubleshooting

### Socket không kết nối
```
1. Kiểm tra SOCKET_SERVER_URL ở frontend
2. Kiểm tra CORS origin ở socket-server.js
3. Kiểm tra JWT token hợp lệ
4. Kiểm tra Laravel API URL ở .env
```

### Token verification fail
```
1. Kiểm tra token chưa expire
2. Kiểm tra ACCESS_TOKEN_SECRET ở .env
3. Kiểm tra Authorization header format
```

### Database không save socket
```
1. Kiểm tra migration đã chạy: php artisan migrate
2. Kiểm tra bảng sockets tồn tại
3. Kiểm tra foreign keys (account_id, guest_id)
4. Kiểm trace logs: tail -f storage/logs/laravel.log
```

---

## 📝 File Changes Summary

### Created Files
1. `socket-server.js` - Node.js WebSocket server
2. `app/Models/Socket.php` - Socket model
3. `app/Services/SocketService.php` - Socket service layer
4. `app/Http/Controllers/SocketController.php` - API controller
5. `app/Events/UserConnected.php` - Connection event
6. `app/Events/UserDisconnected.php` - Disconnect event
7. `database/migrations/2026_05_09_000001_create_sockets_table.php` - DB schema

### Modified Files
1. `routes/api.php` - Thêm socket routes
2. `package.json` - Thêm socket.io dependencies
3. `.env.example` - Thêm socket configuration

---

## 📖 Frontend Usage

### Next.js Frontend Implementation
```typescript
// hooks/useSocket.ts
import { useEffect, useState } from 'react'
import { io, Socket } from 'socket.io-client'

export function useSocket() {
  const [socket, setSocket] = useState<Socket | null>(null)

  useEffect(() => {
    const token = localStorage.getItem('accessToken')
    if (!token) return

    const newSocket = io(process.env.NEXT_PUBLIC_SOCKET_URL, {
      auth: {
        Authorization: `Bearer ${token}`
      }
    })

    // Listen for notifications
    newSocket.on('receive:notification', (data) => {
      console.log('Notification:', data)
      // Show toast/alert
    })

    newSocket.on('user:connected', (data) => {
      console.log('Manager connected:', data)
    })

    setSocket(newSocket)

    return () => newSocket.close()
  }, [])

  return socket
}

// Component usage
export function NotificationCenter() {
  const socket = useSocket()

  const sendNotification = (userId: number, message: string) => {
    socket?.emit('send:notification', {
      targetUserId: userId,
      message,
      type: 'order'
    })
  }

  return <div>...</div>
}
```

---

## ✅ Checklist Chuyển Đổi

- [x] Create Socket Model
- [x] Create Socket Migration
- [x] Create SocketService
- [x] Create SocketController
- [x] Create Events (UserConnected, UserDisconnected)
- [x] Create socket-server.js
- [x] Add socket routes
- [x] Update package.json
- [x] Update .env.example
- [ ] Cài dependencies: `npm install`
- [ ] Chạy migration: `php artisan migrate`
- [ ] Update .env với socket config
- [ ] Test frontend connection
- [ ] Deploy to production

---

## 🔗 References

- Socket.io Documentation: https://socket.io/docs/
- Laravel Broadcasting: https://laravel.com/docs/broadcasting
- JWT Authentication: https://firebase.google.com/docs/auth/tokens-api

