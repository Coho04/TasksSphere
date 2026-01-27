<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Notifications\TaskReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskApiController extends Controller
{
    public function index()
    {
        return Auth::user()->tasks()
            ->where('is_archived', false)
            ->whereNull('completed_at')
            ->orderBy('due_at', 'asc')
            ->get();
    }

    public function occurrences(Request $request)
    {
        $start = $request->query('start', now()->startOfDay()->toDateTimeString());
        $end = $request->query('end', now()->addDays(7)->endOfDay()->toDateTimeString());

        $tasks = Auth::user()->tasks()
            ->where('is_archived', false)
            ->where(function($query) {
                $query->whereNull('completed_at')
                      ->orWhereNotNull('recurrence_rule');
            })
            ->get();

        $allOccurrences = collect();
        foreach ($tasks as $task) {
            $allOccurrences = $allOccurrences->merge($task->getOccurrences($start, $end));
        }

        return $allOccurrences->sortBy('planned_at')->values();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'recurrence_rule' => 'nullable|array',
            'recurrence_rule.frequency' => 'nullable|in:hourly,daily,weekly,monthly',
            'recurrence_rule.interval' => 'nullable|integer|min:1',
            'recurrence_rule.times' => 'nullable|array',
            'recurrence_rule.times.*' => 'string|regex:/^[0-2][0-9]:[0-5][0-9]$/',
            'recurrence_rule.weekdays' => 'nullable|array',
            'recurrence_rule.weekdays.*' => 'integer|min:1|max:7',
            'notify' => 'nullable|boolean',
        ]);

        $task = Auth::user()->tasks()->create($validated);

        if ($request->boolean('notify')) {
            Auth::user()->notify(new TaskReminderNotification($task));
        }

        return $task;
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);
        return $task;
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'recurrence_rule' => 'nullable|array',
            'recurrence_rule.frequency' => 'nullable|in:hourly,daily,weekly,monthly',
            'recurrence_rule.interval' => 'nullable|integer|min:1',
            'recurrence_rule.times' => 'nullable|array',
            'recurrence_rule.times.*' => 'string|regex:/^[0-2][0-9]:[0-5][0-9]$/',
            'recurrence_rule.weekdays' => 'nullable|array',
            'recurrence_rule.weekdays.*' => 'integer|min:1|max:7',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ]);

        $task->update($validated);
        return $task;
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $task->complete($request->input('planned_at'));
        return response()->json(['message' => 'Task completed', 'task' => $task->fresh()]);
    }

    public function skip(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $request->validate(['planned_at' => 'required|date']);
        $task->skip($request->input('planned_at'));
        return response()->json(['message' => 'Occurrence skipped', 'task' => $task->fresh()]);
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();
        return response()->json(['message' => 'Task deleted']);
    }
}
