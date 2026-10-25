<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'task_id', 'assignment_id', 'task', 'started_at', 'stopped_at'];

    protected $casts = ['started_at' => 'datetime', 'stopped_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignment()
    {
        return $this->belongsTo(TaskAssignment::class, 'assignment_id');
    }

    public function workTask()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
