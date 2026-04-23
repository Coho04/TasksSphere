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
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('checklist'); // 'tasks' or 'checklist'
            $table->string('icon', 32)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'type', 'position']);
            $table->index(['team_id', 'type', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
