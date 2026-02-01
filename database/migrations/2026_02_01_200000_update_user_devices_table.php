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
            // fcm_token von text zu string(512) ändern und unique machen
            // In MySQL/MariaDB muss text in string/varchar umgewandelt werden, um einen Unique Index zu setzen
            $table->string('fcm_token', 512)->unique()->change();

            // access_token_id hinzufügen
            $table->unsignedBigInteger('access_token_id')->nullable()->after('fcm_token');

            // Foreign Key hinzufügen
            $table->foreign('access_token_id')->references('id')->on('personal_access_tokens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['access_token_id']);
            $table->dropColumn('access_token_id');
            $table->dropUnique(['fcm_token']);
            $table->text('fcm_token')->change();
        });
    }
};
