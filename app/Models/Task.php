<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'due_at',
        'completed_at',
        'is_active',
        'is_archived',
        'recurrence_rule',
        'recurrence_timezone',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
        'recurrence_rule' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function completions()
    {
        return $this->hasMany(TaskCompletion::class);
    }

    public function isRecurring(): bool
    {
        return !empty($this->recurrence_rule);
    }

    public function isHandledAt($date): bool
    {
        return $this->completions()
            ->where('planned_at', \Illuminate\Support\Carbon::parse($date)->toDateTimeString())
            ->exists();
    }

    public function isSkippedAt($date): bool
    {
        return $this->completions()
            ->where('planned_at', \Illuminate\Support\Carbon::parse($date)->toDateTimeString())
            ->where('is_skipped', true)
            ->exists();
    }

    public function complete($plannedAt = null)
    {
        $plannedAt = $plannedAt ? \Illuminate\Support\Carbon::parse($plannedAt) : ($this->due_at ?: now());

        if ($this->isRecurring()) {
            $this->completions()->updateOrCreate(
                ['planned_at' => $plannedAt],
                ['completed_at' => now(), 'is_skipped' => false]
            );

            // Update due_at to the next uncompleted occurrence
            $this->due_at = $this->calculateNextDueDate($plannedAt);
            $this->save();
        } else {
            $this->completed_at = now();
            $this->save();
        }
    }

    public function skip($plannedAt)
    {
        $plannedAt = \Illuminate\Support\Carbon::parse($plannedAt);

        if ($this->isRecurring()) {
            $this->completions()->updateOrCreate(
                ['planned_at' => $plannedAt],
                ['is_skipped' => true, 'completed_at' => null]
            );

            // If we skipped the current due_at, we should move to the next one
            if ($this->due_at && $this->due_at->equalTo($plannedAt)) {
                $this->due_at = $this->calculateNextDueDate($plannedAt);
                $this->save();
            }
        }
    }

    public function getOccurrences($start, $end)
    {
        $occurrences = collect();
        $start = \Illuminate\Support\Carbon::parse($start);
        $end = \Illuminate\Support\Carbon::parse($end);

        if (!$this->isRecurring()) {
            if (!$this->due_at) {
                // Task without date
                $occurrences->push([
                    'task' => $this,
                    'planned_at' => null,
                    'is_completed' => false
                ]);
            } elseif ($this->due_at->isBefore($start)) {
                if (!$this->completed_at) {
                    // Overdue non-recurring
                    $occurrences->push([
                        'task' => $this,
                        'planned_at' => $this->due_at,
                        'is_completed' => false
                    ]);
                }
            } elseif ($this->due_at->between($start, $end) || $this->due_at->isAfter($end)) {
                // Planned for future (within or after window)
                $occurrences->push([
                    'task' => $this,
                    'planned_at' => $this->due_at,
                    'is_completed' => $this->completed_at !== null
                ]);
            }
            return $occurrences;
        }

        // Recurring logic
        $rule = $this->recurrence_rule;
        $frequency = $rule['frequency'] ?? 'daily';
        $interval = $rule['interval'] ?? 1;
        $times = $rule['times'] ?? [];

        // Wir brauchen einen Startpunkt für die Berechnung. 
        // Da due_at immer auf das nächste fällige Datum aktualisiert wird, 
        // ist es kein zuverlässiger Startpunkt für die gesamte Historie/Zukunft.
        // Aber für die Anzeige der nächsten 7 Tage reicht es, wenn wir beim aktuellen due_at anfangen
        // und AUCH überfällige Termine prüfen.
        
        $current = $this->due_at ? clone $this->due_at : $this->calculateNextDueDate(now()->subMinutes(1));
        
        if (!$current) {
            return $occurrences;
        }
        
        // Zurückgehen zum Fensterstart oder zum ersten unvollständigen Termin
        // Da wir nicht ewig zurückgehen wollen, begrenzen wir das auf z.B. 30 Tage
        $searchStart = $start->copy()->subDays(30);
        
        // Find approximate start point: Start from current due_at and go back as long as it's after searchStart
        $tempDue = clone $current;
        $limit = 100;
        while ($tempDue->isAfter($searchStart) && $limit > 0) {
            // This is tricky because calculateNextDueDate goes forward.
            // For now, let's just start from $this->due_at and go forward until $end.
            // And also check if the current $this->due_at is overdue.
            break; 
        }

        $tempDue = clone $current;
        $limit = 100;
        
        // If the current due_at is before our window, it's overdue
        if ($tempDue->isBefore($start)) {
             // Es ist überfällig. Wir zeigen es an.
             $occurrences->push([
                'task' => $this,
                'planned_at' => clone $tempDue,
                'is_completed' => false // Da es due_at ist, ist es per Def. nicht erledigt
            ]);
            // Dann gehen wir zum nächsten
            $tempDue = $this->calculateNextDueDate($tempDue);
        }

        while ($tempDue && $tempDue->isBefore($end) && $limit > 0) {
            $occurrences->push([
                'task' => $this,
                'planned_at' => clone $tempDue,
                'is_completed' => $this->isHandledAt($tempDue)
            ]);
            
            $tempDue = $this->calculateNextDueDate($tempDue);
            $limit--;
        }

        return $occurrences;
    }

    public function calculateNextDueDate($from = null)
    {
        if (!$this->isRecurring()) {
            return null;
        }

        $rule = $this->recurrence_rule;
        $frequency = $rule['frequency'] ?? 'daily';
        $interval = $rule['interval'] ?? 1;
        $times = $rule['times'] ?? [];
        $weekdays = $rule['weekdays'] ?? []; // Array of integers 1 (Mon) to 7 (Sun)

        $currentDue = $from ? \Illuminate\Support\Carbon::parse($from) : ($this->due_at ? clone $this->due_at : now());

        if (!empty($times)) {
            sort($times);
            $currentTimeStr = $currentDue->format('H:i');

            // Find the next time on the same day
            $nextTime = null;
            foreach ($times as $time) {
                if ($time > $currentTimeStr) {
                    $nextTime = $time;
                    break;
                }
            }

            if ($nextTime) {
                // There is a later time today
                [$hour, $minute] = explode(':', $nextTime);
                return $currentDue->copy()->setTime((int)$hour, (int)$minute);
            } else {
                // No more times today, move to next interval day
                if ($frequency === 'weekly' && !empty($weekdays)) {
                    $nextDue = $currentDue->copy()->addDay();
                    $limit = 7;
                    while (!in_array($nextDue->dayOfWeekIso, $weekdays) && $limit > 0) {
                        $nextDue->addDay();
                        $limit--;
                    }
                } else {
                    $nextDue = $this->addInterval($currentDue, $frequency, $interval);
                }

                [$hour, $minute] = explode(':', $times[0]);
                return $nextDue->copy()->setTime((int)$hour, (int)$minute);
            }
        }

        // No specific times, just add interval
        if ($frequency === 'weekly' && !empty($weekdays)) {
            $nextDue = $currentDue->copy()->addDay();
            $limit = 7;
            while (!in_array($nextDue->dayOfWeekIso, $weekdays) && $limit > 0) {
                $nextDue->addDay();
                $limit--;
            }
            return $nextDue;
        }

        return $this->addInterval($currentDue, $frequency, $interval);
    }

    protected function addInterval($date, $frequency, $interval)
    {
        $newDate = clone $date;
        switch ($frequency) {
            case 'hourly':
                $newDate->addHours($interval);
                break;
            case 'daily':
                $newDate->addDays($interval);
                break;
            case 'weekly':
                $newDate->addWeeks($interval);
                break;
            case 'monthly':
                $newDate->addMonths($interval);
                break;
            default:
                $newDate->addDays($interval);
        }
        return $newDate;
    }
}
