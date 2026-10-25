<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'user_id', 'reviewer_id', 'scope', 'status', 'review_comment', 'submitted_at', 'reviewed_at', 'completed_at', 'score', 'score_comment', 'rework_count'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'completed_at' => 'datetime'];
    public function task() { return $this->belongsTo(Task::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function comments() { return $this->hasMany(TaskComment::class); }
    public function timeEntries() { return $this->hasMany(TimeEntry::class, 'assignment_id'); }
}
