<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    public function current(Request $request)
    {
        return response()->json(self::state($request->user()));
    }

    public function start(Request $request, Task $task, TaskAssignment $assignment)
    {
        $user = $request->user();
        abort_unless($assignment->task_id === $task->id && $assignment->user_id === $user->id && in_array($assignment->status, ['todo', 'in_progress', 'rework']), 403);
        // Блокировка строки пользователя: два быстрых клика или две вкладки не создадут два таймера
        $error = DB::transaction(function () use ($user, $task, $assignment) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $open = $user->timeEntries()->whereNull('stopped_at')->first();
            if ($open && $open->assignment_id === $assignment->id) return null;
            if ($open) return 'Сначала остановите таймер по задаче «'.$open->task.'».';
            $user->timeEntries()->create(['task_id' => $task->id, 'assignment_id' => $assignment->id, 'task' => $task->title, 'started_at' => now()]);
            if ($assignment->status === 'todo') $assignment->update(['status' => 'in_progress']);
            if ($task->status === 'todo') $task->update(['status' => 'in_progress']);
            return null;
        });
        return $this->respond($request, $error, 'Таймер запущен.');
    }

    public function stop(Request $request, ?TimeEntry $timeEntry = null)
    {
        $user = $request->user();
        abort_if($timeEntry && $timeEntry->user_id !== $user->id, 403);
        // Останавливаем любой открытый интервал пользователя: устаревшая вкладка не оставит таймер висеть
        $user->timeEntries()->whereNull('stopped_at')->update(['stopped_at' => now()]);
        return $this->respond($request, null, 'Таймер остановлен.');
    }

    public static function state(User $user): array
    {
        $open = $user->timeEntries()->whereNull('stopped_at')->first();
        if (! $open) return ['running' => false];
        $logged = TimeEntry::where('assignment_id', $open->assignment_id)->whereNotNull('stopped_at')->get()->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at));
        return [
            'running' => true, 'entry_id' => $open->id, 'assignment_id' => $open->assignment_id, 'task' => $open->task,
            'url' => $open->task_id ? route('tasks.show', $open->task_id) : null,
            'started_at' => $open->started_at->toIso8601String(), 'logged' => $logged, 'server_now' => now()->toIso8601String(),
        ];
    }

    private function respond(Request $request, ?string $error, string $success)
    {
        if ($request->expectsJson()) return response()->json(['error' => $error] + self::state($request->user()), $error ? 409 : 200);
        return $error ? back()->withErrors(['task' => $error]) : back()->with('success', $success);
    }
}
