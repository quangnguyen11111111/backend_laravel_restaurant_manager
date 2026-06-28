<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('table_number');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('table_number')->references('number')->on('tables')->onDelete('cascade');
            
            // Ensure no duplicate table mapping for the same order
            $table->unique(['order_id', 'table_number']);
        });

        // Migrate existing table_number from orders table
        $orders = DB::table('orders')->whereNotNull('table_number')->get();
        foreach ($orders as $order) {
            DB::table('order_tables')->insert([
                'order_id' => $order->id,
                'table_number' => $order->table_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_tables');
    }
};
