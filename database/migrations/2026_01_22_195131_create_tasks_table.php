<?php

use App\Models\User;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(User::class);
            $table->string('title');
            $table->text('description')->nullable();

            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);

            $table->json('recurrence_rule')->nullable();
            $table->string('recurrence_timezone')->nullable();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
