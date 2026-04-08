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

### I - Interface Segregation Principle (ISP)

Interface được chia nhỏ theo domain/use-case, không bắt class phải implement method không dùng.

Vị trí:

- `AuthRepositoryInterface` chỉ chứa method liên quan auth token/account email.
- `AccountRepositoryInterface` chỉ chứa method cho account lifecycle.
- `GuestRepositoryInterface` chỉ chứa method cho guest/table query.

### D - Dependency Inversion Principle (DIP)

Tầng cao (Service, Controller) phụ thuộc abstraction thay vì concrete.

Vị trí:

- `AuthService` phụ thuộc `AuthRepositoryInterface`.
- `AccountService` phụ thuộc `AccountRepositoryInterface`.
- `GuestService` phụ thuộc `GuestRepositoryInterface`.
- Container bind abstraction -> concrete trong `AppServiceProvider`.

## Kết quả kiến trúc hiện tại

- Controller đã mỏng và dễ đọc hơn.
- Luồng nghiệp vụ tập trung trong Service.
- Truy cập data được đóng gói trong Repository.
- Cấu trúc sẵn sàng cho unit test theo từng lớp.

## Hướng tiếp theo để hoàn thiện

- Bổ sung unit test cho `AuthService`, `AccountService`, `GuestService`.
- Bổ sung feature test cho auth/account endpoints.
- Cân nhắc thêm API Resources để chuẩn hóa output schema.
