# Backend Laravel Restaurant Manager

## Mục tiêu tái cấu trúc

Code được tái cấu trúc để:

- Giảm kiến trúc controller-heavy.
- Tách rõ business logic, data access logic, và HTTP layer.
- Tăng khả năng mở rộng, test, và bảo trì.

## Design Pattern đã áp dụng

### 1) Service Pattern

Business logic được đưa vào Service, controller chỉ điều phối request/response.

Nơi đã áp dụng:

- `app/Services/AuthService.php`: login, logout, refresh token, Google OAuth, token workflow.
- `app/Services/AccountService.php`: use-case quản lý nhân viên, cập nhật profile, đổi mật khẩu.
- `app/Services/GuestService.php`: use-case tạo guest và lấy danh sách guest theo bộ lọc.
- `app/Services/DishService.php`: use-case CRUD món ăn, giữ nguyên logic từ Node.js.
- `app/Services/TableService.php`: use-case CRUD bàn, bao gồm logic đổi token và thu hồi refresh token của guest theo bàn.

Giá trị nhận được:

- Controller mỏng (thin controller).
- Logic trung tâm nằm một chỗ, dễ test theo use-case.

### 2) Repository Pattern (narrow repository)

Data access được tách qua interface + implementation cụ thể theo use-case, không dùng generic repository quá lớn.

Nơi đã áp dụng:

- `app/Repositories/Contracts/AuthRepositoryInterface.php`
- `app/Repositories/AuthRepository.php`
- `app/Repositories/Contracts/AccountRepositoryInterface.php`
- `app/Repositories/AccountRepository.php`
- `app/Repositories/Contracts/GuestRepositoryInterface.php`
- `app/Repositories/GuestRepository.php`
- `app/Repositories/Contracts/DishRepositoryInterface.php`
- `app/Repositories/DishRepository.php`
- `app/Repositories/Contracts/TableRepositoryInterface.php`
- `app/Repositories/TableRepository.php`

Giá trị nhận được:

- Service không phụ thuộc trực tiếp Eloquent query.
- Dễ thay đổi chiến lược truy vấn mà không sửa service/controller.

### 3) Dependency Injection + IoC Container

Các abstraction được bind vào implementation trong container.

Nơi đã áp dụng:

- `app/Providers/AppServiceProvider.php`

Bindings:

- `AuthRepositoryInterface -> AuthRepository`
- `AccountRepositoryInterface -> AccountRepository`
- `GuestRepositoryInterface -> GuestRepository`
- `DishRepositoryInterface -> DishRepository`
- `TableRepositoryInterface -> TableRepository`

Giá trị nhận được:

- Controller/Service phụ thuộc abstraction thay vì concrete.
- Tăng khả năng mock khi unit test.

### 4) Exception-based Service Flow

Service throw domain/service exception, controller map exception thành response HTTP đồng nhất.

Nơi đã áp dụng:

- `app/Exceptions/AuthServiceException.php`
- `app/Exceptions/ServiceException.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/AccountController.php`
- `app/Http/Controllers/DishController.php`
- `app/Http/Controllers/TableController.php`

Giá trị nhận được:

- Giảm logic if/else trả lỗi trong controller.
- Error shape ổn định, dễ frontend xử lý.

## SOLID đang tuân thủ và vị trí cụ thể

### S - Single Responsibility Principle (SRP)

Mỗi class có 1 lý do thay đổi rõ ràng:

- Controller: xử lý HTTP layer (request/response).
- Service: xử lý business rule/use-case.
- Repository: xử lý query/persistence.
- Exception class: chuẩn hóa lỗi service.

Vị trí:

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/AccountController.php`
- `app/Http/Controllers/DishController.php`
- `app/Http/Controllers/TableController.php`
- `app/Services/*.php`
- `app/Repositories/*.php`

### O - Open/Closed Principle (OCP)

Hệ thống mở rộng bằng cách thêm implementation mới mà không cần sửa nhiều code hiện có.

Ví dụ:

- Có thể thêm `CachedAccountRepository` hoặc `ExternalAuthRepository` implement cùng interface.
- Service và controller vẫn giữ nguyên vì làm việc qua abstraction.

Vị trí:

- `app/Repositories/Contracts/*.php`
- `app/Providers/AppServiceProvider.php`

### L - Liskov Substitution Principle (LSP)

Mọi implementation repository có thể thay thế cho interface mà không phá vỡ hợp đồng hành vi mong đợi.

Vị trí:

- `AuthRepository` thay thế `AuthRepositoryInterface`
- `AccountRepository` thay thế `AccountRepositoryInterface`
- `GuestRepository` thay thế `GuestRepositoryInterface`
- `DishRepository` thay thế `DishRepositoryInterface`
- `TableRepository` thay thế `TableRepositoryInterface`

### I - Interface Segregation Principle (ISP)

Interface được chia nhỏ theo domain/use-case, không bắt class phải implement method không dùng.

Vị trí:

- `AuthRepositoryInterface` chỉ chứa method liên quan auth token/account email.
- `AccountRepositoryInterface` chỉ chứa method cho account lifecycle.
- `GuestRepositoryInterface` chỉ chứa method cho guest/table query và token cleanup của guest.
- `DishRepositoryInterface` chỉ chứa method cho truy vấn/CRUD món ăn.
- `TableRepositoryInterface` chỉ chứa method cho truy vấn/CRUD bàn.

### D - Dependency Inversion Principle (DIP)

Tầng cao (Service, Controller) phụ thuộc abstraction thay vì concrete.

Vị trí:

- `AuthService` phụ thuộc `AuthRepositoryInterface`.
- `AccountService` phụ thuộc `AccountRepositoryInterface`.
- `GuestService` phụ thuộc `GuestRepositoryInterface`.
- `DishService` phụ thuộc `DishRepositoryInterface`.
- `TableService` phụ thuộc `TableRepositoryInterface` và `GuestRepositoryInterface`.
- Container bind abstraction -> concrete trong `AppServiceProvider`.

## Hướng dẫn chuyển giao từ Node.js + SQLite sang Laravel

Mục tiêu: giữ nguyên API path, giữ nguyên logic xử lý, và tái cấu trúc theo SOLID.

### 1) Khóa cứng API contract trước khi migrate

Từ dự án Node (`NextJs-Super-BackEnd-main`), lập bảng contract cho từng API gồm:

- HTTP method + path (ví dụ: `GET /dishes`, `POST /dishes`, `PUT /dishes/:id`).
- Auth rule (public hay yêu cầu đăng nhập/quyền).
- Validation rule của params/body/query.
- Response shape thành công/lỗi (bao gồm `message`, `data`, `errors`, `statusCode`).

Khi chuyển sang Laravel, implementation có thể đổi kiến trúc nhưng contract không đổi.

### 2) Chuyển schema SQLite (Prisma) sang migration Laravel

Map trực tiếp model Prisma sang migration Laravel theo nguyên tắc:

- Tên bảng và cột giữ tương thích tối đa.
- Giá trị enum/default giữ nguyên.
- Quan hệ FK giữ hành vi onDelete/onUpdate như cũ.

Ví dụ module Dish đã chuyển:

- Prisma `Dish` -> migration `database/migrations/2026_04_22_000006_create_dishes_table.php`
- `status` default `Available`
- có đầy đủ `created_at`, `updated_at`

Ví dụ module Table đang dùng migration tương thích Node:

- Prisma `Table` -> migration `database/migrations/2026_04_07_000004_create_tables_table.php`
- `number` là khóa chính
- `status` default `Available`
- `token` được sinh ngẫu nhiên khi tạo bàn

### 3) Chuẩn hóa kiến trúc theo SOLID trong Laravel

Mỗi module API nên đi theo flow:

- Route (khai báo path + middleware)
- FormRequest (validation)
- Controller (thin controller)
- Service (business use-case)
- Repository Interface + Implementation (data access)
- Model (Eloquent mapping)

Điều này giúp giữ logic nhưng cải thiện maintainability so với controller-heavy style.

### 4) Giữ nguyên auth semantics từ Node

Node đang có pattern: Login AND (Owner OR Employee) cho API ghi dữ liệu. Khi migrate sang Laravel:

- dùng `jwt.auth` để xác thực.
- dùng `role:Owner,Employee` để mô phỏng `requireOwnerHook OR requireEmployeeHook`.

### 5) Giữ nguyên response contract

Với API thành công, trả về đúng cấu trúc:

- `message`: giữ nguyên thông điệp nghiệp vụ.
- `data`: giữ cấu trúc object/list theo API Node.

Với API lỗi từ service, dùng exception có status code + error list để frontend không cần đổi nhiều.

### 6) Chiến lược migrate dữ liệu từ SQLite

Gợi ý quy trình an toàn:

1. Export từng bảng từ SQLite (CSV/SQL dump).
2. Chuẩn hóa enum value, định dạng thời gian, khóa ngoại.
3. Import vào DB của Laravel sau khi chạy `php artisan migrate`.
4. Đối soát record count + spot-check dữ liệu theo từng module.

Nếu muốn không downtime, có thể chạy dual-write tạm thời ở một số API quan trọng trong giai đoạn chuyển tiếp.

### 7) Checklist migrate theo module

- [x] Auth
- [x] Account
- [x] Guest
- [x] Dish
- [x] Table
- [ ] Order
- [ ] Indicator
- [ ] Media/Static

### 8) Chi tiết module Dish đã chuyển đổi

Đường dẫn API được giữ nguyên:

- `GET /dishes`
- `GET /dishes/{id}`
- `POST /dishes`
- `PUT /dishes/{id}`
- `DELETE /dishes/{id}`

Auth giữ nguyên logic từ Node:

- GET public.
- POST/PUT/DELETE yêu cầu `jwt.auth` + `role:Owner,Employee`.

Validation giữ tương đương:

- `name`: required, max 256
- `price`: số nguyên dương
- `description`: required, max 10000
- `image`: URL hợp lệ
- `status`: `Available | Unavailable | Hidden` (optional)

Các file chính:

- `routes/api.php`
- `app/Http/Controllers/DishController.php`
- `app/Http/Requests/CreateDishRequest.php`
- `app/Http/Requests/UpdateDishRequest.php`
- `app/Services/DishService.php`
- `app/Repositories/Contracts/DishRepositoryInterface.php`
- `app/Repositories/DishRepository.php`
- `app/Models/Dish.php`
- `database/migrations/2026_04_22_000006_create_dishes_table.php`

### 9) Chi tiết module Table đã chuyển đổi

Đường dẫn API được giữ nguyên:

- `GET /tables`
- `GET /tables/{number}`
- `POST /tables`
- `PUT /tables/{number}`
- `DELETE /tables/{number}`

Auth giữ nguyên logic từ Node:

- GET public.
- POST/PUT/DELETE yêu cầu `jwt.auth` + `role:Owner,Employee`.

Validation giữ tương đương:

- `number`: số nguyên dương
- `capacity`: số nguyên dương
- `status`: `Available | Hidden | Reserved` (optional)
- `changeToken` (khi update): boolean bắt buộc

Logic nghiệp vụ quan trọng giữ nguyên:

- Khi tạo bàn: backend tự sinh `token` ngẫu nhiên.
- Khi cập nhật với `changeToken=true`: đổi token mới và xóa toàn bộ `refresh_token`, `refresh_token_expires_at` của guest đang gắn với bàn đó.

Các file chính:

- `routes/api.php`
- `app/Http/Controllers/TableController.php`
- `app/Http/Requests/CreateTableRequest.php`
- `app/Http/Requests/UpdateTableRequest.php`
- `app/Services/TableService.php`
- `app/Repositories/Contracts/TableRepositoryInterface.php`
- `app/Repositories/TableRepository.php`
- `app/Models/Table.php`
- `database/migrations/2026_04_07_000004_create_tables_table.php`

## Kết quả kiến trúc hiện tại

- Controller đã mỏng và dễ đọc hơn.
- Luồng nghiệp vụ tập trung trong Service.
- Truy cập data được đóng gói trong Repository.
- Cấu trúc sẵn sàng cho unit test theo từng lớp.
- Module Dish đã được chuyển từ Node.js sang Laravel với API path và logic tương thích.
- Module Table đã được chuyển từ Node.js sang Laravel với API path và logic tương thích.

## Hướng tiếp theo để hoàn thiện

- Bổ sung unit test cho `AuthService`, `AccountService`, `GuestService`, `DishService`, `TableService`.
- Bổ sung feature test cho auth/account/dish/table endpoints.
- Cân nhắc thêm API Resources để chuẩn hóa output schema.

Lệnh tạo migration
php artisan migrate
Lệnh tạo seeder
php artisan db:seed
Lệnh chạy server
php artisan serve

**Order & Guest Migration**: Order và Guest được tách theo Service + Repository. `app/Services/OrderService.php` implement toàn bộ luồng tạo đơn, snapshot món, cập nhật trạng thái, và thanh toán; `app/Http/Controllers/OrderController.php` là thin controller. `app/Http/Controllers/GuestController.php` giữ logic token (access/refresh) tương tự Node, bao gồm refresh token giữ nguyên expiry và xóa refresh token khi token bàn đổi.

**Áp dụng Patterns**: Service pattern cho business logic, Repository pattern cho data access, FormRequest cho validation, Exception-based error flow cho mapping lỗi service -> HTTP response.
