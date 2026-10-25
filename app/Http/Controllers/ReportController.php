<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, string $tab = 'overview')
    {
        abort_unless($request->user()->isManager(), 403);
        [$from, $to] = $this->period($request);
        $people = User::where('role', 'employee')->orderBy('name')->get();
        $entries = TimeEntry::with(['user', 'workTask.tags', 'workTask.assigner', 'assignment.reviewer'])->whereNotNull('stopped_at')->whereBetween('started_at', [$from, $to])->latest('started_at')->get();
        $finished = TaskAssignment::with(['task.tags', 'task.assigner', 'user', 'reviewer', 'timeEntries'])->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $to])->get();
        $metrics = $people->mapWithKeys(fn ($user) => [$user->id => $this->metrics($user, $finished->where('user_id', $user->id), $entries->where('user_id', $user->id))]);
        $data = compact('tab', 'from', 'to', 'people', 'entries', 'finished', 'metrics');

        if ($tab === 'overview') {
            $data['created'] = Task::whereBetween('created_at', [$from, $to])->count();
            $data['doneTasks'] = Task::whereBetween('completed_at', [$from, $to])->count();
            $data['openOverdue'] = Task::where('status', '!=', 'done')->whereDate('due_date', '<', now()->toDateString())->with('assignments.user')->get();
            $data['statusCounts'] = Task::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
            $data['weeks'] = $this->weeks($from, $to)->map(fn ($week) => [
                'label' => $week->format('d.m'),
                'done' => $finished->filter(fn ($a) => $a->completed_at->between($week, $week->copy()->endOfWeek()))->count(),
                'hours' => $entries->filter(fn ($e) => $e->started_at->between($week, $week->copy()->endOfWeek()))->sum(fn ($e) => $this->seconds($e)) / 3600,
            ]);
        }
        if ($tab === 'time') {
            $data['weeks'] = $this->weeks($from, $to);
            $data['matrix'] = $people->mapWithKeys(fn ($user) => [$user->id => $data['weeks']->map(fn ($week) => $entries->where('user_id', $user->id)->filter(fn ($e) => $e->started_at->between($week, $week->copy()->endOfWeek()))->sum(fn ($e) => $this->seconds($e)))]);
            $data['byTask'] = $entries->groupBy('task_id')->map(fn ($items) => ['task' => $items->first()->workTask, 'title' => $items->first()->task, 'seconds' => $items->sum(fn ($e) => $this->seconds($e)), 'people' => $items->pluck('user.name')->unique()->join(', ')])->sortByDesc('seconds')->take(15);
        }
        if ($tab === 'deadlines') {
            $data['late'] = $finished->filter(fn ($a) => $a->task->due_date && $a->completed_at->gt($a->task->due_date->copy()->endOfDay()))->sortByDesc(fn ($a) => $a->completed_at->diffInDays($a->task->due_date));
            $data['openOverdue'] = TaskAssignment::with(['task', 'user'])->where('status', '!=', 'done')->whereHas('task', fn ($q) => $q->whereDate('due_date', '<', now()->toDateString()))->get();
            $data['upcoming'] = Task::with('assignments.user')->where('status', '!=', 'done')->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)])->orderBy('due_date')->get();
        }
        if ($tab === 'quality') {
            $data['reviewers'] = User::whereIn('id', $finished->pluck('reviewer_id')->filter()->unique())->get()->map(function ($reviewer) use ($finished) {
                $items = $finished->where('reviewer_id', $reviewer->id);
                return ['user' => $reviewer, 'count' => $items->count(), 'reworks' => $items->sum('rework_count'), 'score' => $items->whereNotNull('score')->avg('score'),
                    'turnaround' => $items->filter(fn ($a) => $a->submitted_at && $a->reviewed_at)->avg(fn ($a) => $a->submitted_at->diffInMinutes($a->reviewed_at) / 60)];
            });
            $data['worst'] = $finished->where('rework_count', '>', 0)->sortByDesc('rework_count')->take(12);
        }
        if ($tab === 'tags') {
            $data['tagRows'] = Tag::with('tasks.assignments')->get()->map(function ($tag) use ($entries, $finished) {
                $ids = $tag->tasks->pluck('id');
                return ['tag' => $tag, 'total' => $ids->count(), 'done' => $tag->tasks->where('status', 'done')->count(), 'overdue' => $tag->tasks->filter->isOverdue()->count(),
                    'seconds' => $entries->whereIn('task_id', $ids)->sum(fn ($e) => $this->seconds($e)), 'reworks' => $finished->whereIn('task_id', $ids)->sum('rework_count')];
            })->sortByDesc('seconds');
        }
        return view('reports.index', $data);
    }

    public function person(Request $request, User $user)
    {
        abort_unless($request->user()->isManager(), 403);
        [$from, $to] = $this->period($request);
        $entries = TimeEntry::with('workTask')->where('user_id', $user->id)->whereNotNull('stopped_at')->whereBetween('started_at', [$from, $to])->get();
        $finished = TaskAssignment::with(['task.tags', 'task.assigner', 'reviewer', 'timeEntries'])->where('user_id', $user->id)->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $to])->latest('completed_at')->get();
        $active = TaskAssignment::with(['task', 'reviewer'])->where('user_id', $user->id)->where('status', '!=', 'done')->get();
        $m = $this->metrics($user, $finished, $entries);
        $weeks = $this->weeks($from, $to)->map(fn ($week) => ['label' => $week->format('d.m'), 'hours' => $entries->filter(fn ($e) => $e->started_at->between($week, $week->copy()->endOfWeek()))->sum(fn ($e) => $this->seconds($e)) / 3600]);
        $feedback = $user->taskAssignments()->whereNotNull('review_comment')->with('task')->latest('reviewed_at')->limit(10)->get();
        return view('reports.person', compact('user', 'from', 'to', 'm', 'finished', 'active', 'weeks', 'feedback'));
    }

    private function metrics(User $user, $finished, $entries): array
    {
        $done = $finished->count();
        $onTime = $finished->filter(fn ($a) => ! $a->task->due_date || $a->completed_at->lte($a->task->due_date->copy()->endOfDay()))->count();
        $clean = $finished->where('rework_count', 0)->count();
        $score = $finished->whereNotNull('score')->avg('score');
        // Рейтинг: 40% сроки, 30% приём с первого раза, 30% оценка ревьюера
        $rating = $done ? (int) round($onTime / $done * 40 + $clean / $done * 30 + ($score ?? 3) / 5 * 30) : null;
        return [
            'seconds' => $entries->sum(fn ($e) => $this->seconds($e)), 'done' => $done, 'on_time' => $onTime, 'clean' => $clean, 'reworks' => $finished->sum('rework_count'), 'score' => $score, 'rating' => $rating,
            'overdue_open' => TaskAssignment::where('user_id', $user->id)->where('status', '!=', 'done')->whereHas('task', fn ($q) => $q->whereDate('due_date', '<', now()->toDateString()))->count(),
        ];
    }

    private function period(Request $request): array
    {
        $from = Carbon::parse($request->input('from', now()->subMonths(3)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        return [$from, $to];
    }

    private function weeks(Carbon $from, Carbon $to)
    {
        return collect(CarbonPeriod::create($from->copy()->startOfWeek(), '1 week', $to))->map(fn ($d) => $d->copy());
    }

    private function seconds(TimeEntry $entry): int
    {
        return $entry->started_at->diffInSeconds($entry->stopped_at ?? now());
    }
}
