# 🍽️ Restaurant Manager Backend

## 📖 Giới thiệu (Introduction)
Dự án Backend cho Hệ thống Quản lý Nhà hàng, được xây dựng trên nền tảng **Laravel 12** và **PHP 8.2+**. 
Hệ thống cung cấp các RESTful APIs mạnh mẽ và an toàn phục vụ cho các ứng dụng Frontend (Web/App) để thực hiện các nghiệp vụ: quản lý thực đơn, đặt bàn, gọi món, quản lý nhân viên và khách hàng.
Đặc biệt, dự án tích hợp một **Node.js Socket Server** độc lập để xử lý các tính năng thời gian thực (Real-time) như cập nhật trạng thái đơn hàng ngay lập tức cho nhà bếp và khách hàng.

## 🚀 Công nghệ sử dụng (Tech Stack)
- **Framework:** Laravel 12
- **Ngôn ngữ:** PHP 8.2+, Node.js (cho Socket server)
- **Authentication:** Laravel Sanctum & JWT (`firebase/php-jwt`)
- **Real-time:** Socket.IO
- **Database:** SQLite (Mặc định, hỗ trợ cấu hình MySQL/PostgreSQL dễ dàng)

## ⚙️ Hướng dẫn cài đặt & Chạy dự án (Setup & Usage)

### 1. Yêu cầu hệ thống
- PHP >= 8.2
- Composer
- Node.js & npm

### 2. Cài đặt các gói phụ thuộc
```bash
# Cài đặt PHP dependencies
composer install

# Cài đặt Node.js dependencies (cho Socket Server và Vite)
npm install
```

### 3. Cấu hình môi trường (Environment Setup)
Hệ thống sử dụng file `.env` cho cấu hình chung.

```bash
# Copy file cấu hình môi trường
cp .env.example .env

# Tạo app key
php artisan key:generate

# Tạo cơ sở dữ liệu SQLite (nếu chưa có)
touch database/database.sqlite

# Chạy migration và seed dữ liệu mẫu
php artisan migrate --seed
```

### 4. Khởi chạy Server
Hệ thống cung cấp sẵn script lệnh chạy đồng thời cả **Laravel API**, **Queue worker**, và các service khác.

Chỉ cần chạy lệnh sau:
```bash
npm run dev
```

*Hoặc nếu bạn muốn khởi chạy từng thành phần độc lập:*
- **Laravel API Server:** `php artisan serve` (Chạy ở cổng 8000)
- **Node.js Socket Server:** `node socket-server.js` (Chạy ở cổng 3001)

---

## 📡 Cấu trúc tính năng & API (API Overview)
Hệ thống chia làm nhiều nhóm tính năng (routes được định nghĩa tại `routes/api.php`):
- **Auth (`/api/auth`):** Đăng nhập, đăng nhập qua Google, refresh token, logout.
- **Accounts & Guests (`/api/accounts`, `/api/guests`):** Quản lý nhân viên (Owner/Manager/Staff) và khách hàng vãng lai.
- **Categories & Dishes (`/api/categories`, `/api/dishes`):** Quản lý thực đơn, upload hình ảnh món ăn, phân loại danh mục.
- **Tables & Reservations (`/api/tables`, `/api/reservations`):** Quản lý danh sách bàn, trạng thái sức chứa và nghiệp vụ đặt chỗ.
- **Orders & Kitchen (`/api/orders`, `/api/kitchen`):** Theo dõi đơn hàng, xử lý trạng thái bếp (đang nấu, đã xong) và thanh toán.
- **Sockets (`/api/sockets`):** Các API nội bộ giúp Node.js Socket Server giao tiếp với Laravel (quản lý connection, broadcast tin nhắn).

---

## 🏗️ Kiến trúc & Design Pattern
Dự án được thiết kế theo hướng module hóa cao, dễ dàng bảo trì và mở rộng, tuân thủ chặt chẽ các nguyên tắc **SOLID**.

### 1) Service Pattern
Business logic được đưa vào Service, controller chỉ đóng vai trò điều phối request/response.
- **Nơi áp dụng:** `app/Services/` (AuthService, AccountService, DishService, TableService...)
- **Giá trị mang lại:** Giúp Controller mỏng (thin controller), tập trung logic vào một chỗ và dễ dàng test theo use-case.

### 2) Repository Pattern (Narrow Repository)
Data access được tách qua interface + implementation cụ thể theo use-case, tránh dùng generic repository quá cồng kềnh.
- **Nơi áp dụng:** `app/Repositories/` (AuthRepository, DishRepository...)
- **Giá trị mang lại:** Service không phụ thuộc trực tiếp vào Eloquent query, dễ dàng thay đổi chiến lược truy xuất dữ liệu mà không ảnh hưởng tới logic nghiệp vụ.

### 3) Dependency Injection + IoC Container
Các abstraction (Interface) được bind vào implementation trong container.
- **Nơi áp dụng:** `app/Providers/AppServiceProvider.php`
- **Giá trị mang lại:** Tăng khả năng mock khi Unit Test, controller/service chỉ cần gọi abstraction.

### 4) Exception-based Service Flow
Service throw domain/service exception, controller map exception thành response HTTP đồng nhất.
- **Nơi áp dụng:** `app/Exceptions/` và các Controllers.
- **Giá trị mang lại:** Giảm if/else trong controller, error shape trả về cho frontend luôn đồng nhất.

---

## 📐 Nguyên tắc SOLID áp dụng trong dự án

### S - Single Responsibility Principle (SRP)
Mỗi class có một lý do thay đổi rõ ràng:
- **Controller:** Xử lý HTTP layer (request/response).
- **Service:** Xử lý business rules / use-case.
- **Repository:** Xử lý data query / persistence.
- **Exception:** Chuẩn hóa lỗi hệ thống.

### O - Open/Closed Principle (OCP)
Hệ thống mở rộng bằng cách thêm implementation mới mà không sửa code cũ.
*Ví dụ: Có thể thêm `CachedAccountRepository` implement `AccountRepositoryInterface` mà không làm vỡ các Service hiện tại.*

### L - Liskov Substitution Principle (LSP)
Mọi implementation repository có thể thay thế cho interface mà không phá vỡ hợp đồng hành vi mong đợi.
*Vị trí: `AccountRepository` hoàn toàn thay thế được `AccountRepositoryInterface`.*

### I - Interface Segregation Principle (ISP)
Interface được chia nhỏ theo domain/use-case, không bắt class implement những method thừa.
*Vị trí: `GuestRepositoryInterface` chỉ chứa method truy vấn bàn và token cho guest.*

### D - Dependency Inversion Principle (DIP)
Tầng cao phụ thuộc abstraction thay vì concrete.
*Vị trí: `AuthService` phụ thuộc `AuthRepositoryInterface`, không phụ thuộc trực tiếp vào model `User`.*

---

## 🤝 Đóng góp (Contributing)
Nếu bạn có bất kỳ đóng góp nào để phát triển hệ thống, vui lòng tạo Pull Request hoặc mở Issue. Mọi đóng góp về refactoring hoặc tối ưu hiệu năng đều được chào đón!
