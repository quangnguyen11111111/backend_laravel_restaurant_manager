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
        Schema::table('tables', function (Blueprint $table) {
            $table->string('group_id')->nullable()->after('capacity');
            $table->integer('group_order')->nullable()->after('group_id');
            $table->integer('max_capacity')->nullable()->after('group_order');
        });

        // Set max_capacity = capacity for existing records
        DB::statement('UPDATE `tables` SET `max_capacity` = `capacity`');
        
        // Make max_capacity not null if needed (optional, we can just leave it as is, but it's better to make it not nullable if there is data)
        Schema::table('tables', function (Blueprint $table) {
            $table->integer('max_capacity')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'group_order', 'max_capacity']);
        });
    }
};
