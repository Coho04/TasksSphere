<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'planned_at',
        'completed_at',
        'is_skipped',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_skipped' => 'boolean',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
