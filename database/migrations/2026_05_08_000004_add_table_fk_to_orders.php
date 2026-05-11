<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `orders` MODIFY `table_number` INT UNSIGNED NULL');
            DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_table_number_foreign` FOREIGN KEY (`table_number`) REFERENCES `tables`(`number`) ON DELETE SET NULL');
            return;
        }

        // Fallback: skip for SQLite (no ALTER TABLE ADD CONSTRAINT support)
        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('table_number')->nullable()->change();
            $table->foreign('table_number')->references('number')->on('tables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `orders` DROP FOREIGN KEY `orders_table_number_foreign`');
            DB::statement('ALTER TABLE `orders` MODIFY `table_number` INT NOT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['table_number']);
            $table->integer('table_number')->nullable(false)->change();
        });
    }
};
