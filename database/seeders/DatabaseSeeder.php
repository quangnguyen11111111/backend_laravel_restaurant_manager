<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create initial Owner account (matching Node.js behavior)
        $ownerEmail = env('INITIAL_EMAIL_OWNER', 'admin@order.com');
        $ownerPassword = env('INITIAL_PASSWORD_OWNER', '123456');

        // Only create if no accounts exist
        if (Account::count() === 0) {
            Account::create([
                'name' => 'Owner',
                'email' => $ownerEmail,
                'password' => Hash::make($ownerPassword),
                'role' => Account::ROLE_OWNER,
            ]);

            $this->command->info("Khởi tạo tài khoản chủ quán thành công: {$ownerEmail}|{$ownerPassword}");
        } else {
            $this->command->info('Đã có tài khoản trong database, bỏ qua việc tạo tài khoản Owner.');
        }
    }
}
