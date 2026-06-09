<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks for dropping tables with relationships
        Schema::disableForeignKeyConstraints();

        // 1. Drop old tables
        Schema::dropIfExists('orders');
        Schema::dropIfExists('dish_snapshots');

        // 2. Modify tables table (status enum)
        // Since enum modification can be tricky in some DBs, we'll just drop and re-add the column if sqlite is not used,
        // but for broader support, we can just use string instead of enum or stick to enum.
        // Actually, let's use string for status to be safe, or just alter the enum.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `tables` MODIFY COLUMN `status` ENUM('Available', 'Hidden', 'Reserved', 'Occupied') DEFAULT 'Available'");
        } else {
            // SQLite doesn't support modifying enums easily, we'll ignore or recreate for sqlite.
            // Assuming MySQL/MariaDB based on previous migrations.
        }

        // 3. Create NEW orders table (Master Order)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('table_number')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable(); // The host guest who opened the session
            $table->integer('guest_count')->default(1);
            $table->string('session_pin')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->timestamp('reservation_time')->nullable();
            $table->enum('status', ['Pending_Arrival', 'Active', 'Paid', 'Cancelled'])->default('Pending_Arrival');
            $table->timestamps();

            $table->foreign('table_number')->references('number')->on('tables')->onDelete('set null');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('set null');
        });

        // 4. Create order_details table
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->unsignedBigInteger('dish_id')->nullable();
            $table->string('dish_name');
            $table->integer('dish_price');
            $table->string('dish_image')->nullable();
            $table->integer('quantity')->default(1);
            $table->enum('status', ['Pending', 'Processing', 'Delivered', 'Cancelled'])->default('Pending');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('order_handler_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('set null');
            $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('set null');
            $table->foreign('order_handler_id')->references('id')->on('accounts')->onDelete('set null');
        });

        // 5. Update guests table - replace table_number with order_id
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['table_number']);
            $table->dropColumn('table_number');
            $table->unsignedBigInteger('order_id')->nullable()->after('name');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Down migration can just be a manual reverse if needed, or left empty/partial as it's a major refactor.
        // We will just drop the new tables.
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('order_details');

        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
            $table->unsignedInteger('table_number')->nullable()->after('name');
            $table->foreign('table_number')->references('number')->on('tables')->onDelete('set null');
        });

        Schema::dropIfExists('orders');
        
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `tables` MODIFY COLUMN `status` ENUM('Available', 'Hidden', 'Reserved') DEFAULT 'Available'");
        }

        // We are not fully reversing to dish_snapshots as data is lost anyway.
        Schema::enableForeignKeyConstraints();
    }
};
