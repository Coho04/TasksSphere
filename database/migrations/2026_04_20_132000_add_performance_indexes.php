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
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'is_archived', 'completed_at', 'due_at'], 'tasks_user_archived_completed_due_index');
            $table->index(['due_at', 'is_active', 'is_archived', 'completed_at'], 'tasks_due_active_archived_completed_index');
        });

        Schema::table('task_completions', function (Blueprint $table) {
            $table->index(['task_id', 'planned_at'], 'task_completions_task_planned_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_archived_completed_due_index');
            $table->dropIndex('tasks_due_active_archived_completed_index');
        });

        Schema::table('task_completions', function (Blueprint $table) {
            $table->dropIndex('task_completions_task_planned_index');
        });
    }
};
