<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Models\TaskAssignment;
use App\Models\TaskChecklist;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function show(Request $request, Task $task)
    {
        $task->load(['tags', 'parent', 'children.assignments.user', 'assigner', 'checklist', 'materials', 'assignments.user', 'assignments.reviewer', 'assignments.timeEntries', 'comments.user', 'comments.assignment']);
        $this->ensureCanSee($request, $task);
        return view('tasks.show', compact('task'));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Task::with(['tags', 'assigner', 'assignments.user', 'parent'])->withCount('children');
        if (! $user->isManager()) $query->where(fn ($q) => $q->doesntHave('assignments')->orWhereHas('assignments', fn ($a) => $a->where('user_id', $user->id)->orWhere('reviewer_id', $user->id)));
        self::applyFilters($request, $query);
        $tasks = $query->orderByRaw(self::PRIORITY_ORDER)->orderByRaw('due_date is null')->orderBy('due_date')->paginate(30)->withQueryString();
        return view('tasks.index', ['tasks' => $tasks, 'tags' => Tag::orderBy('name')->get(), 'people' => User::orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        return view('tasks.create', [
            'tags' => Tag::orderBy('name')->get(), 'people' => User::orderBy('name')->get(),
            'tasks' => Task::where('status', '!=', 'done')->orderBy('title')->get(['id', 'title', 'parent_id']), 'parentId' => $request->integer('parent') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'priority' => ['required', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'], 'checklist' => ['nullable', 'string'], 'materials.*' => ['nullable', 'file', 'max:10240'],
            'assignees' => ['nullable', 'array'], 'assignees.*' => ['exists:users,id'], 'scopes' => ['nullable', 'array'], 'reviewer_id' => ['nullable', 'exists:users,id'],
            'parent_id' => ['nullable', 'exists:tasks,id'], 'tags' => ['nullable', 'array'], 'tags.*' => ['exists:tags,id'], 'new_tags' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'], 'children.*' => ['exists:tasks,id'], 'checklist_items' => ['nullable', 'array'],
        ]);
        $assignees = array_values(array_unique($data['assignees'] ?? []));
        $task = Task::create(collect($data)->except(['checklist', 'checklist_items', 'materials', 'assignees', 'scopes', 'reviewer_id', 'tags', 'new_tags', 'children'])->all() + ['assigned_by' => $request->user()->id, 'assigned_to' => $assignees[0] ?? null]);
        foreach ($assignees as $userId) $task->assignments()->create(['user_id' => $userId, 'reviewer_id' => $data['reviewer_id'] ?? $request->user()->id, 'scope' => trim($data['scopes'][$userId] ?? '') ?: 'Часть работы по задаче']);
        $lines = array_merge((array) ($data['checklist_items'] ?? []), preg_split('/\r\n|\r|\n/', $data['checklist'] ?? ''));
        foreach (array_values(array_filter(array_map('trim', $lines))) as $position => $title) $task->checklist()->create(['title' => $title, 'position' => $position]);
        foreach ($request->file('materials', []) as $file) $task->materials()->create(['name' => $file->getClientOriginalName(), 'path' => $file->store('task-materials', 'public'), 'mime_type' => $file->getMimeType()]);
        $this->syncTags($task, $data);
        if (! empty($data['children'])) Task::whereIn('id', $data['children'])->whereKeyNot($task->id)->update(['parent_id' => $task->id]);
        return redirect()->route('tasks.show', $task)->with('success', 'Задача создана и назначена исполнителям.');
    }

    public function update(Request $request, Task $task)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'priority' => ['required', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'], 'checklist' => ['nullable', 'string'], 'materials.*' => ['nullable', 'file', 'max:10240'],
            'parent_id' => ['nullable', 'exists:tasks,id', 'not_in:'.$task->id], 'tags' => ['nullable', 'array'], 'tags.*' => ['exists:tags,id'], 'new_tags' => ['nullable', 'string', 'max:255'],
        ]);
        $task->update(collect($data)->except(['checklist', 'materials', 'tags', 'new_tags'])->all());
        $this->syncTags($task, $data + ['tags' => []]);
        $position = (int) $task->checklist()->max('position') + 1;
        foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['checklist'] ?? ''))) as $title) $task->checklist()->create(['title' => $title, 'position' => $position++]);
        foreach ($request->file('materials', []) as $file) $task->materials()->create(['name' => $file->getClientOriginalName(), 'path' => $file->store('task-materials', 'public'), 'mime_type' => $file->getMimeType()]);
        return back()->with('success', 'Задача обновлена.');
    }

    public function reassign(Request $request, Task $task, TaskAssignment $assignment)
    {
        abort_unless($request->user()->isManager() && $assignment->task_id === $task->id, 403);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'reviewer_id' => ['nullable', 'exists:users,id']]);
        if ($task->assignments()->where('user_id', $data['user_id'])->whereKeyNot($assignment->id)->exists()) return back()->withErrors(['task' => 'Этот сотрудник уже участвует в задаче.']);
        $assignment->timeEntries()->whereNull('stopped_at')->update(['stopped_at' => now()]);
        $from = $assignment->user->name;
        $assignment->update(['user_id' => $data['user_id'], 'reviewer_id' => $data['reviewer_id'] ?? $assignment->reviewer_id, 'status' => $assignment->status === 'done' ? 'done' : 'todo']);
        $task->comments()->create(['assignment_id' => $assignment->id, 'user_id' => $request->user()->id, 'body' => "Участок «{$assignment->scope}» передан: {$from} → {$assignment->fresh()->user->name}."]);
        $task->update(['assigned_to' => $task->assignments()->value('user_id')]);
        $this->syncStatus($task);
        return back()->with('success', 'Задача передана другому исполнителю.');
    }

    public function removeAssignment(Request $request, Task $task, TaskAssignment $assignment)
    {
        abort_unless($request->user()->isManager() && $assignment->task_id === $task->id, 403);
        $assignment->timeEntries()->whereNull('stopped_at')->update(['stopped_at' => now()]);
        $assignment->delete();
        $task->update(['assigned_to' => $task->assignments()->value('user_id')]);
        $this->syncStatus($task);
        return back()->with('success', 'Участник снят с задачи.');
    }

    public function addAssignment(Request $request, Task $task)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'scope' => ['required', 'string', 'max:255'], 'reviewer_id' => ['nullable', 'exists:users,id']]);
        $task->assignments()->updateOrCreate(['user_id' => $data['user_id']], ['scope' => $data['scope'], 'reviewer_id' => $data['reviewer_id'] ?? $request->user()->id]);
        $task->update(['assigned_to' => $task->assignments()->value('user_id')]);
        return back()->with('success', 'Участник добавлен к задаче.');
    }

    public function claim(Request $request, Task $task)
    {
        abort_if($request->user()->isManager(), 403);
        abort_if($task->assignments()->exists() || $task->status === 'done', 403);
        $task->assignments()->create(['user_id' => $request->user()->id, 'reviewer_id' => $task->assigned_by, 'scope' => 'Основная часть задачи']);
        $task->update(['assigned_to' => $request->user()->id]);
        return back()->with('success', 'Задача закреплена за вами.');
    }

    public function submit(Request $request, Task $task, TaskAssignment $assignment)
    {
        $this->ensureAssignment($request, $task, $assignment);
        $assignment->timeEntries()->whereNull('stopped_at')->update(['stopped_at' => now()]);
        $assignment->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->syncStatus($task);
        return back()->with('success', 'Работа передана на ревью.');
    }

    public function review(Request $request, Task $task, TaskAssignment $assignment)
    {
        abort_unless($assignment->task_id === $task->id && ($request->user()->isManager() || $assignment->reviewer_id === $request->user()->id), 403);
        $data = $request->validate(['decision' => ['required', 'in:accepted,rework'], 'review_comment' => ['nullable', 'string', 'max:3000'], 'score' => ['nullable', 'integer', 'between:1,5'], 'score_comment' => ['nullable', 'string', 'max:3000']]);
        $assignment->update(['status' => $data['decision'] === 'accepted' ? 'done' : 'rework', 'review_comment' => $data['review_comment'] ?? null, 'reviewed_at' => now(), 'completed_at' => $data['decision'] === 'accepted' ? now() : null, 'score' => $data['decision'] === 'accepted' ? ($data['score'] ?? null) : null, 'score_comment' => $data['decision'] === 'accepted' ? ($data['score_comment'] ?? null) : null, 'rework_count' => $data['decision'] === 'rework' ? $assignment->rework_count + 1 : $assignment->rework_count]);
        $this->syncStatus($task);
        return back()->with('success', $data['decision'] === 'accepted' ? 'Результат принят.' : 'Задача возвращена на доработку.');
    }

    public function toggleChecklist(Request $request, Task $task, TaskChecklist $checklist)
    {
        abort_unless($checklist->task_id === $task->id, 404); $this->ensureCanContribute($request, $task);
        $checklist->update(['is_completed' => ! $checklist->is_completed]); return back();
    }

    public function comment(Request $request, Task $task)
    {
        $this->ensureCanSee($request, $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000'], 'assignment_id' => ['nullable', 'exists:task_assignments,id']]);
        if (!empty($data['assignment_id'])) abort_unless($task->assignments()->whereKey($data['assignment_id'])->exists(), 404);
        $task->comments()->create($data + ['user_id' => $request->user()->id]);
        return back()->with('success', 'Комментарий добавлен.');
    }

    public const PRIORITY_ORDER = "case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 else 3 end";

    public static function applyFilters(Request $request, $query): void
    {
        if ($search = trim((string) $request->input('q'))) $query->where(fn ($q) => $q->whereLike('title', "%$search%")->orWhereLike('description', "%$search%"));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('priority')) $query->whereIn('priority', (array) $request->input('priority'));
        if ($request->filled('tag')) $query->whereHas('tags', fn ($q) => $q->whereKey($request->input('tag')));
        if ($request->filled('assignee')) $query->whereHas('assignments', fn ($q) => $q->where('user_id', $request->input('assignee')));
        if ($request->boolean('overdue')) $query->where('status', '!=', 'done')->whereDate('due_date', '<', now()->toDateString());
        if ($request->boolean('epics')) $query->has('children');
    }

    private function syncTags(Task $task, array $data): void
    {
        $ids = $data['tags'] ?? [];
        $palette = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6', '#ef4444', '#14b8a6'];
        foreach (array_filter(array_map('trim', explode(',', $data['new_tags'] ?? ''))) as $name) $ids[] = Tag::firstOrCreate(['name' => mb_strtolower($name)], ['color' => $palette[crc32($name) % count($palette)]])->id;
        $task->tags()->sync(array_unique($ids));
    }

    private function ensureCanSee(Request $request, Task $task): void
    {
        abort_unless($request->user()->isManager() || ! $task->assignments()->exists() || $task->assignments()->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('reviewer_id', $request->user()->id))->exists(), 403);
    }

    private function ensureCanContribute(Request $request, Task $task): void
    {
        abort_unless($request->user()->isManager() || $task->assignments()->where('user_id', $request->user()->id)->exists(), 403);
    }

    private function ensureAssignment(Request $request, Task $task, TaskAssignment $assignment): void
    {
        abort_unless($assignment->task_id === $task->id && $assignment->user_id === $request->user()->id, 403);
    }

    private function syncStatus(Task $task): void
    {
        $states = $task->assignments()->pluck('status');
        $status = $states->isNotEmpty() && $states->every(fn ($state) => $state === 'done') ? 'done' : ($states->contains('submitted') ? 'review' : ($states->contains(fn ($state) => in_array($state, ['in_progress', 'rework'])) ? 'in_progress' : 'todo'));
        $task->update(['status' => $status, 'completed_at' => $status === 'done' ? now() : null]);
    }
}
