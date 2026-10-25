<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'title', 'description', 'priority', 'status', 'assigned_to', 'assigned_by', 'due_date', 'completed_at'];
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function assigner() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function checklist() { return $this->hasMany(TaskChecklist::class)->orderBy('position'); }
    public function materials() { return $this->hasMany(TaskMaterial::class); }
    public function timeEntries() { return $this->hasMany(TimeEntry::class); }
    public function assignments() { return $this->hasMany(TaskAssignment::class); }
    public function tags() { return $this->belongsToMany(Tag::class); }
    public function parent() { return $this->belongsTo(Task::class, 'parent_id'); }
    public function children() { return $this->hasMany(Task::class, 'parent_id'); }
    public function isOverdue(): bool { return $this->due_date && $this->status !== 'done' && $this->due_date->endOfDay()->isPast(); }
    public function comments() { return $this->hasMany(TaskComment::class)->latest(); }
}
