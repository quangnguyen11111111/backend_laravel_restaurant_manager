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
        if (Schema::hasColumn('accounts', 'avatar_s3_key')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('avatar_s3_key')->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('accounts', 'avatar_s3_key')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('avatar_s3_key');
        });
    }
};
