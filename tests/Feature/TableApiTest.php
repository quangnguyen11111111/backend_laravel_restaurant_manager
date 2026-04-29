<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\Table;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TableApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_tables_sorted_by_created_at_desc(): void
    {
        $oldTable = Table::query()->create([
            'number' => 1,
            'capacity' => 4,
            'status' => Table::STATUS_AVAILABLE,
            'token' => 'oldtoken',
        ]);

        $newTable = Table::query()->create([
            'number' => 2,
            'capacity' => 6,
            'status' => Table::STATUS_RESERVED,
            'token' => 'newtoken',
        ]);

        $oldTable->forceFill([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ])->save();

        $newTable->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->getJson('/api/tables');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Lấy danh sách bàn thành công!')
            ->assertJsonPath('data.0.number', $newTable->number)
            ->assertJsonPath('data.1.number', $oldTable->number);
    }

    public function test_owner_can_create_update_and_delete_table_and_change_token_resets_guest_tokens(): void
    {
        $owner = $this->createAccount(Account::ROLE_OWNER, 'owner-table@example.com');
        $accessToken = $this->createAccessToken($owner->id, $owner->role);

        $createResponse = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/tables', [
                'number' => 10,
                'capacity' => 4,
            ]);

        $createResponse
            ->assertOk()
            ->assertJsonPath('message', 'Tạo bàn thành công!')
            ->assertJsonPath('data.number', 10)
            ->assertJsonPath('data.status', Table::STATUS_AVAILABLE);

        $oldToken = (string) $createResponse->json('data.token');

        Guest::query()->create([
            'name' => 'Guest A',
            'table_number' => 10,
            'refresh_token' => 'guest-refresh-token',
            'refresh_token_expires_at' => now()->addDay(),
        ]);

        $updateResponse = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->putJson('/api/tables/10', [
                'changeToken' => true,
                'capacity' => 8,
                'status' => Table::STATUS_HIDDEN,
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('message', 'Cập nhật bàn thành công!')
            ->assertJsonPath('data.number', 10)
            ->assertJsonPath('data.capacity', 8)
            ->assertJsonPath('data.status', Table::STATUS_HIDDEN);

        $newToken = (string) $updateResponse->json('data.token');
        $this->assertNotSame($oldToken, $newToken);

        $guest = Guest::query()->where('table_number', 10)->firstOrFail();
        $this->assertNull($guest->refresh_token);
        $this->assertNull($guest->refresh_token_expires_at);

        $deleteResponse = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->deleteJson('/api/tables/10');

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('message', 'Xóa bàn thành công!')
            ->assertJsonPath('data.number', 10);
    }

    public function test_employee_can_create_table(): void
    {
        $employee = $this->createAccount(Account::ROLE_EMPLOYEE, 'employee-table@example.com');
        $accessToken = $this->createAccessToken($employee->id, $employee->role);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/tables', [
                'number' => 20,
                'capacity' => 4,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Tạo bàn thành công!')
            ->assertJsonPath('data.number', 20);
    }

    public function test_create_table_requires_authentication(): void
    {
        $response = $this->postJson('/api/tables', [
            'number' => 30,
            'capacity' => 4,
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Không nhận được access token');
    }

    public function test_create_table_with_duplicate_number_returns_422(): void
    {
        $owner = $this->createAccount(Account::ROLE_OWNER, 'owner-duplicate-table@example.com');
        $accessToken = $this->createAccessToken($owner->id, $owner->role);

        Table::query()->create([
            'number' => 99,
            'capacity' => 2,
            'status' => Table::STATUS_AVAILABLE,
            'token' => 'duplicate-token',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/tables', [
                'number' => 99,
                'capacity' => 6,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Lỗi xảy ra khi xác thực dữ liệu...')
            ->assertJsonPath('errors.0.field', 'number')
            ->assertJsonPath('errors.0.message', 'Số bàn này đã tồn tại');
    }

    private function createAccount(string $role, string $email): Account
    {
        return Account::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => $role,
        ]);
    }

    private function createAccessToken(int $userId, string $role): string
    {
        $now = time();

        return JWT::encode([
            'userId' => $userId,
            'role' => $role,
            'tokenType' => 'AccessToken',
            'iat' => $now,
            'exp' => $now + 3600,
        ], config('auth.access_token_secret'), 'HS256');
    }
}
