<?php

namespace Tests\Unit;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskWeekdayTest extends TestCase
{
    public function test_weekly_recurrence_with_weekdays()
    {
        $task = new Task;
        // Thursday 2026-01-22 08:00
        $task->due_at = Carbon::parse('2026-01-22 08:00:00');
        $task->recurrence_rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [1, 3], // Mon, Wed
        ];

        // Next should be Monday 2026-01-26 08:00
        $nextDue = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-26 08:00:00', $nextDue->format('Y-m-d H:i:s'));
        $this->assertEquals(1, $nextDue->dayOfWeekIso);
    }

    public function test_weekly_recurrence_with_multiple_times_and_weekdays()
    {
        $task = new Task;
        // Monday 2026-01-26 08:00
        $task->due_at = Carbon::parse('2026-01-26 08:00:00');
        $task->recurrence_rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'times' => ['08:00', '20:00'],
            'weekdays' => [1, 3], // Mon, Wed
        ];

        // 1. Completion -> should move to 20:00 same day (Mon)
        $nextDue = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-26 20:00:00', $nextDue->format('Y-m-d H:i:s'));

        // 2. Completion -> should move to 08:00 on Wed 2026-01-28
        $task->due_at = $nextDue;
        $nextDue2 = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-28 08:00:00', $nextDue2->format('Y-m-d H:i:s'));
        $this->assertEquals(3, $nextDue2->dayOfWeekIso);
    }

    public function test_weekly_recurrence_skips_to_next_week()
    {
        $task = new Task;
        // Friday 2026-01-23 08:00
        $task->due_at = Carbon::parse('2026-01-23 08:00:00');
        $task->recurrence_rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [1], // Only Mon
        ];

        // Next should be Monday 2026-01-26
        $nextDue = $task->calculateNextDueDate();
        $this->assertEquals('2026-01-26 08:00:00', $nextDue->format('Y-m-d H:i:s'));
    }
}
