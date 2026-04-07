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
        Schema::create('tables', function (Blueprint $table) {
            $table->unsignedInteger('number')->primary();
            $table->integer('capacity');
            $table->enum('status', ['Available', 'Hidden', 'Reserved'])->default('Available');
            $table->string('token');
            $table->timestamps();
        });

        // Add foreign key for guests table
        Schema::table('guests', function (Blueprint $table) {
            $table->foreign('table_number')
                ->references('number')
                ->on('tables')
                ->onDelete('set null')
                ->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['table_number']);
        });

        Schema::dropIfExists('tables');
    }
};
