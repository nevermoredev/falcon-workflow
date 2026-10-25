<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tags = Tag::orderBy('name')->get();
        if ($user->isManager()) {
            $query = Task::with(['tags', 'assigner', 'assignments.user', 'checklist'])->withCount('children');
            TaskController::applyFilters($request, $query);
            // Готовое показываем только за последние 2 недели, чтобы доска не разрасталась
            $query->where(fn ($q) => $q->where('status', '!=', 'done')->orWhere('completed_at', '>=', now()->subDays(14)));
            $tasks = $query->orderByRaw(TaskController::PRIORITY_ORDER)->orderByRaw('due_date is null')->orderBy('due_date')->get();
            return view('manager.dashboard', ['tasks' => $tasks, 'tags' => $tags, 'people' => User::where('role', 'employee')->orderBy('name')->get()]);
        }

        $tab = $request->input('tab', 'active');
        $activeEntry = $user->timeEntries()->whereNull('stopped_at')->latest('started_at')->first();
        $filter = function ($q) use ($request) {
            if ($request->filled('priority')) $q->whereIn('priority', (array) $request->input('priority'));
            if ($request->filled('tag')) $q->whereHas('tags', fn ($t) => $t->whereKey($request->input('tag')));
            if ($search = trim((string) $request->input('q'))) $q->whereLike('title', "%$search%");
            if ($request->boolean('overdue')) $q->whereDate('due_date', '<', now()->toDateString());
        };
        $assignments = TaskAssignment::with(['task.assigner', 'task.tags', 'reviewer', 'timeEntries'])->where('user_id', $user->id)->where('status', '!=', 'done')->whereHas('task', $filter)->get()
            ->sortBy(fn ($a) => [['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$a->task->priority], $a->task->due_date?->timestamp ?? PHP_INT_MAX]);
        $queue = Task::with(['assigner', 'tags'])->doesntHave('assignments')->doesntHave('children')->where('status', 'todo')->where($filter)->orderByRaw(TaskController::PRIORITY_ORDER)->get();
        $reviews = TaskAssignment::with(['task.tags', 'user'])->where('reviewer_id', $user->id)->where('status', 'submitted')->whereHas('task', $filter)->get();
        $history = TaskAssignment::with(['task.assigner', 'task.tags', 'reviewer', 'timeEntries'])->where('user_id', $user->id)->where('status', 'done')->whereHas('task', $filter)->latest('completed_at')->limit(50)->get();
        $weekSeconds = $user->timeEntries()->where('started_at', '>=', now()->startOfWeek())->get()->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at ?? now()));
        return view('employee.dashboard', compact('tab', 'tags', 'activeEntry', 'assignments', 'queue', 'reviews', 'history', 'weekSeconds'));
    }
}
