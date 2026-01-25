<?php

namespace Tests\Unit;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskRecurrenceTest extends TestCase
{
    public function test_hourly_recurrence_preserves_time()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = ['frequency' => 'hourly', 'interval' => 12];

        $nextDue = $task->calculateNextDueDate();

        $this->assertEquals('2026-01-23 20:00:00', $nextDue->format('Y-m-d H:i:s'));
    }

    public function test_daily_recurrence_preserves_time()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = ['frequency' => 'daily', 'interval' => 1];

        $nextDue = $task->calculateNextDueDate();

        $this->assertEquals('2026-01-24 08:00:00', $nextDue->format('Y-m-d H:i:s'));
    }

    public function test_weekly_recurrence_preserves_time()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = ['frequency' => 'weekly', 'interval' => 1];

        $nextDue = $task->calculateNextDueDate();

        $this->assertEquals('2026-01-30 08:00:00', $nextDue->format('Y-m-d H:i:s'));
    }

    public function test_monthly_recurrence_preserves_time()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = ['frequency' => 'monthly', 'interval' => 1];

        $nextDue = $task->calculateNextDueDate();

        $this->assertEquals('2026-02-23 08:00:00', $nextDue->format('Y-m-d H:i:s'));
    }

    public function test_multiple_times_per_day_recurrence()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times' => ['08:00', '10:00']
        ];

        // 1. Completion -> should move to 10:00 same day
        $nextDue = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-23 10:00:00', $nextDue->format('Y-m-d H:i:s'));

        // 2. Completion (simulated) -> should move to 08:00 next day
        $task->due_at = $nextDue;
        $nextDue2 = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-24 08:00:00', $nextDue2->format('Y-m-d H:i:s'));
    }

    public function test_multiple_times_with_larger_interval()
    {
        $task = new Task();
        $task->due_at = Carbon::parse('2026-01-23 10:00:00');
        $task->recurrence_rule = [
            'frequency' => 'daily',
            'interval' => 2, // every 2 days
            'times' => ['08:00', '10:00']
        ];

        // Currently at 10:00 (last time of the day)
        // Next should be 08:00 in 2 days
        $nextDue = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-25 08:00:00', $nextDue->format('Y-m-d H:i:s'));
    }
}
