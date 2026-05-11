# Socket Server Setup Guide (Nhanh Gọn)

## 🚀 Quick Start

### Step 1: Backend Setup
```bash
# Terminal 1: Cài dependencies
npm install

# Run migration
php artisan migrate

# Update .env
SOCKET_PORT=3001
LARAVEL_API_URL=http://localhost:8000/api
FRONTEND_URL=http://localhost:3000

# Run Laravel
php artisan serve
```

### Step 2: Socket Server
```bash
# Terminal 2: Chạy socket server
npm run socket:dev
```

### Step 3: Frontend Connection
```typescript
// frontend_nextjs_restaurant_manager/lib/socket.ts
import { io } from 'socket.io-client'

let socket: any = null

export function initSocket(token: string) {
  if (socket?.connected) return socket

  socket = io(process.env.NEXT_PUBLIC_SOCKET_URL || 'http://localhost:3001', {
    auth: {
      Authorization: `Bearer ${token}`
    }
  })

  socket.on('connect', () => {
    console.log('✅ Connected to socket server')
  })

  socket.on('disconnect', () => {
    console.log('❌ Disconnected from socket server')
  })

  socket.on('receive:notification', (data) => {
    console.log('📬 Notification:', data)
  })

  socket.on('user:connected', (data) => {
    console.log('👤 User connected:', data)
  })

  socket.on('user:disconnected', (data) => {
    console.log('👤 User disconnected:', data)
  })

  socket.on('receive:broadcast', (data) => {
    console.log('📢 Broadcast:', data)
  })

  return socket
}

export function sendNotification(targetUserId: number, message: string) {
  if (!socket) return

  socket.emit('send:notification', {
    targetUserId,
    message,
    type: 'notification'
  })
}

export function broadcastToManagers(message: string, data?: any) {
  if (!socket) return

  socket.emit('broadcast:managers', {
    message,
    ...data
  })
}

export function getSocket() {
  return socket
}
```

## 📡 Usage Example

```typescript
// app/page.tsx hoặc component nào
'use client'
import { useEffect } from 'react'
import { initSocket, sendNotification } from '@/lib/socket'

export default function Home() {
  useEffect(() => {
    const token = localStorage.getItem('accessToken')
    if (token) {
      initSocket(token)
    }
  }, [])

  const handleSendNotification = () => {
    // Gửi notification tới user có ID = 5
    sendNotification(5, 'Đơn hàng của bạn đã sẵn sàng!')
  }

  return (
    <button onClick={handleSendNotification}>
      Send Notification
    </button>
  )
}
```

## ✅ Verify Connection

### 1. Check Socket Server Running
```bash
curl http://localhost:3001/socket.io/
# Response: 0
```

### 2. Check Laravel API
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/sockets/managers
```

### 3. Check Database
```bash
sqlite3 database.sqlite
SELECT * FROM sockets;
```

## 🔧 Common Issues

| Issue | Solution |
|-------|----------|
| Socket not connecting | Check token, CORS, Laravel API URL |
| Token expired | Refresh token trước khi connect |
| Database not saving | Run `php artisan migrate` |
| Cannot find user | Verify user exists in accounts/guests table |

