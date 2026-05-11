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
        Schema::create('sockets', function (Blueprint $table) {
            $table->id();
            $table->string('socket_id')->unique();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');

            // Indexes
            $table->index('account_id');
            $table->index('guest_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sockets');
    }
};
