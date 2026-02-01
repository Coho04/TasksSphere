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
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['access_token_id']);
            $table->dropColumn('access_token_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('access_token_id')->nullable()->after('fcm_token');
            $table->foreign('access_token_id')->references('id')->on('personal_access_tokens')->onDelete('cascade');
        });
    }
};
