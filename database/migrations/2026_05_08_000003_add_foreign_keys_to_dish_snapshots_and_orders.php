<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dish_snapshots', function (Blueprint $table) {
            $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('set null');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('order_handler_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['order_handler_id']);
        });

        Schema::table('dish_snapshots', function (Blueprint $table) {
            $table->dropForeign(['dish_id']);
        });
    }
};
