<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dish_snapshot_id');
            $table->unsignedBigInteger('guest_id');
            $table->integer('quantity')->default(1);
            $table->integer('table_number');
            $table->unsignedBigInteger('order_handler_id')->nullable();
            $table->enum('status', ['Pending', 'Processing', 'Delivered', 'Paid'])->default('Pending');
            $table->timestamps();

            $table->foreign('dish_snapshot_id')->references('id')->on('dish_snapshots')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
