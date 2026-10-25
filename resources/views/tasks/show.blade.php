@extends('layouts.app')
@section('content')
@php
    $me = auth()->user();
    $isManager = $me->isManager();
    $mine = $task->assignments->firstWhere('user_id', $me->id);
    $active = $mine?->timeEntries->first(fn ($entry) => is_null($entry->stopped_at));
    $employees = $isManager ? \App\Models\User::orderBy('name')->get() : collect();
    $priorities = ['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий', 'urgent' => 'Срочный'];
    $taskStates = ['todo' => 'К выполнению', 'in_progress' => 'В работе', 'review' => 'На ревью', 'done' => 'Готово'];
    $states = ['todo' => 'К выполнению', 'in_progress' => 'В работе', 'submitted' => 'На ревью', 'rework' => 'Доработка', 'done' => 'Принято'];
    $overdue = $task->due_date && $task->status !== 'done' && $task->due_date->endOfDay()->isPast();
    $spent = fn ($assignment) => $assignment->timeEntries->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at ?? now()));
    $hm = fn ($s) => sprintf('%d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <a href="{{ route('dashboard') }}" class="back-link">← К задачам</a>
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <span class="priority-label">{{ mb_strtoupper($priorities[$task->priority]) }}</span>
            <span class="task-status status-{{ $task->status }}">{{ mb_strtoupper($taskStates[$task->status] ?? $task->status) }}</span>
            @if($overdue)<span class="task-status status-rework">ПРОСРОЧЕНО</span>@endif
        </div>
        @if($task->parent)<div class="small mt-2">↳ часть задачи <a href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->title }}</a></div>@endif
        <h1>{{ $task->title }}</h1>
        <div class="d-flex gap-1 flex-wrap">@foreach($task->tags as $tag)<span class="tag" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</div>
    </div>
    <div class="task-actions d-flex gap-2">
        @if(! $isManager && $task->assignments->isEmpty() && $task->status === 'todo')
            <form method="post" action="{{ route('tasks.claim', $task) }}">@csrf<button class="btn btn-falcon">Взять задачу</button></form>
        @elseif($mine && in_array($mine->status, ['todo', 'in_progress', 'rework']))
            <div class="timer-box" data-timer data-assignment="{{ $mine->id }}" data-start-url="{{ route('timer.start', [$task, $mine]) }}" data-logged="{{ $mine->timeEntries->whereNotNull('stopped_at')->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at)) }}">
                <span class="timer-clock" data-clock>0:00:00</span>
                <button type="button" class="btn {{ $active ? 'btn-stop' : 'btn-falcon' }}" data-toggle>{{ $active ? '■ Стоп' : '▶ Старт' }}</button>
            </div>
            <form method="post" action="{{ route('tasks.assignments.submit', [$task, $mine]) }}">@csrf<button class="btn btn-outline-falcon h-100">Сдать на ревью</button></form>
        @endif
    </div>
</div>

<div class="row g-4">
<div class="col-lg-8">
    <section class="card falcon-card mb-4"><div class="card-body p-4">
        <span class="eyebrow">ОПИСАНИЕ</span>
        <div class="task-description mt-3" style="white-space: pre-line">{{ $task->description ?: 'Описание не добавлено.' }}</div>
        <div class="details-row mt-4">
            <div><small>Поставил</small><b>{{ $task->assigner->name }}</b></div>
            <div><small>Исполнители</small><b>{{ $task->assignments->pluck('user.name')->join(', ') ?: 'Общая очередь' }}</b></div>
            <div><small>Дедлайн</small><b class="{{ $overdue ? 'text-danger' : '' }}">{{ $task->due_date?->format('d.m.Y') ?? 'Не задан' }}</b></div>
        </div>
    </div></section>

    @if($task->children->isNotEmpty() || $isManager)
    <section class="card falcon-card mb-4"><div class="card-body p-4">
        @php($childDone = $task->children->where('status', 'done')->count())
        <div class="d-flex justify-content-between align-items-center">
            <span class="eyebrow">ПОДЗАДАЧИ {{ $task->children->isNotEmpty() ? "· $childDone/{$task->children->count()}" : '' }}</span>
            @if($isManager)<a class="btn btn-outline-falcon btn-sm" href="{{ route('tasks.create', ['parent' => $task->id]) }}">＋ Подзадача</a>@endif
        </div>
        @if($task->children->isNotEmpty())<div class="progress-mini my-2"><i style="width: {{ round($childDone / $task->children->count() * 100) }}%"></i></div>@endif
        @foreach($task->children as $child)
            <a href="{{ route('tasks.show', $child) }}" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none text-reset">
                <span>{{ $child->title }} <small class="text-muted">{{ $child->assignments->pluck('user.name')->join(', ') }}</small></span>
                <span class="task-status status-{{ $child->status }}">{{ $taskStates[$child->status] }}</span>
            </a>
        @endforeach
    </div></section>
    @endif

    <section class="card falcon-card mb-4"><div class="card-body p-4">
        <span class="eyebrow">УЧАСТКИ РАБОТЫ</span>
        @forelse($task->assignments as $assignment)
            <div class="border rounded p-3 mt-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div><b>{{ $assignment->user->name }}</b> — {{ $assignment->scope }}</div>
                    <span class="task-status status-{{ $assignment->status }}">{{ $states[$assignment->status] }}</span>
                </div>
                <small class="text-muted">
                    Ревьюер: {{ $assignment->reviewer?->name ?? '—' }} · Время: {{ $hm($spent($assignment)) }} · Возвратов: {{ $assignment->rework_count }}
                    @if($assignment->submitted_at) · Сдано: {{ $assignment->submitted_at->format('d.m H:i') }}@endif
                    @if($assignment->completed_at) · Принято: {{ $assignment->completed_at->format('d.m H:i') }}@endif
                    @if($assignment->score) · Оценка: {{ $assignment->score }}/5 @endif
                </small>
                @if($assignment->review_comment)<div class="small mt-2"><b>Ревью:</b> {{ $assignment->review_comment }}</div>@endif

                @if($assignment->status === 'submitted' && ($isManager || $assignment->reviewer_id === $me->id))
                    <form method="post" action="{{ route('tasks.assignments.review', [$task, $assignment]) }}" class="row g-2 mt-2">@csrf
                        <div class="col-12"><textarea class="form-control form-control-sm" name="review_comment" rows="2" placeholder="Комментарий ревью: что не так / что хорошо"></textarea></div>
                        <div class="col-sm-3"><select class="form-select form-select-sm" name="score"><option value="">Оценка</option>@for($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
                        <div class="col-sm-9 d-flex gap-2">
                            <button class="btn btn-falcon btn-sm" name="decision" value="accepted">✓ Принять</button>
                            <button class="btn btn-stop btn-sm" name="decision" value="rework">↺ На доработку</button>
                        </div>
                    </form>
                @endif

                @if($isManager)
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <form method="post" action="{{ route('tasks.assignments.reassign', [$task, $assignment]) }}" class="d-flex gap-2">@csrf
                            <select class="form-select form-select-sm" name="user_id">@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($employee->id === $assignment->user_id)>{{ $employee->name }}</option>@endforeach</select>
                            <select class="form-select form-select-sm" name="reviewer_id">@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($employee->id === $assignment->reviewer_id)>Ревью: {{ $employee->name }}</option>@endforeach</select>
                            <button class="btn btn-outline-falcon btn-sm text-nowrap">Передать</button>
                        </form>
                        <form method="post" action="{{ route('tasks.assignments.destroy', [$task, $assignment]) }}" onsubmit="return confirm('Снять исполнителя с задачи?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger">Снять</button></form>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-muted mt-3 mb-0">Пока никто не взял задачу.</p>
        @endforelse
    </div></section>

    <section class="card falcon-card mb-4"><div class="card-body p-4">
        @php($doneCount = $task->checklist->where('is_completed', true)->count())
        <span class="eyebrow">ЧЕК-ЛИСТ {{ $task->checklist->isNotEmpty() ? "· $doneCount/{$task->checklist->count()}" : '' }}</span>
        <div class="checklist mt-3">
            @forelse($task->checklist as $item)
                <form method="post" action="{{ route('tasks.checklist.toggle', [$task, $item]) }}" class="check-row">@csrf
                    <button class="check-button {{ $item->is_completed ? 'checked' : '' }}" title="Переключить" @disabled(! $isManager && ! $mine)>{{ $item->is_completed ? '✓' : '' }}</button>
                    <span class="{{ $item->is_completed ? 'text-muted' : '' }}">{{ $item->title }}</span>
                </form>
            @empty
                <p class="text-muted mb-0">Чек-лист не добавлен.</p>
            @endforelse
        </div>
    </div></section>

    <section class="card falcon-card mb-4"><div class="card-body p-4">
        <span class="eyebrow">МАТЕРИАЛЫ</span>
        <div class="material-list mt-3">
            @forelse($task->materials as $material)
                <a href="{{ asset('storage/'.$material->path) }}" target="_blank" class="material-item">
                    @if(str_starts_with((string) $material->mime_type, 'image/'))<img src="{{ asset('storage/'.$material->path) }}" alt="" style="max-height: 80px; border-radius: 6px">@else ▣ @endif
                    <span>{{ $material->name }}</span><small>Открыть</small>
                </a>
            @empty
                <p class="text-muted mb-0">Вложений нет.</p>
            @endforelse
        </div>
    </div></section>

    <section class="card falcon-card"><div class="card-body p-4">
        <span class="eyebrow">ОБСУЖДЕНИЕ И ЖУРНАЛ</span>
        @if($isManager || $mine || $task->assignments->contains('reviewer_id', $me->id))
            <form method="post" action="{{ route('tasks.comments.store', $task) }}" class="mt-3">@csrf
                <textarea class="form-control mb-2" name="body" rows="2" required placeholder="Комментарий, описание сделанного, вопрос"></textarea>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm w-auto" name="assignment_id">
                        <option value="">К задаче целиком</option>
                        @foreach($task->assignments as $assignment)<option value="{{ $assignment->id }}" @selected($mine?->id === $assignment->id)>К участку: {{ $assignment->user->name }} — {{ Str::limit($assignment->scope, 30) }}</option>@endforeach
                    </select>
                    <button class="btn btn-falcon btn-sm">Отправить</button>
                </div>
            </form>
        @endif
        <div class="mt-3">
            @forelse($task->comments as $comment)
                <div class="border-bottom py-2">
                    <small class="text-muted">{{ $comment->user->name }} · {{ $comment->created_at->format('d.m.Y H:i') }}@if($comment->assignment) · участок «{{ Str::limit($comment->assignment->scope, 30) }}»@endif</small>
                    <div style="white-space: pre-line">{{ $comment->body }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">Комментариев нет.</p>
            @endforelse
        </div>
    </div></section>
</div>

<div class="col-lg-4">
    @if($isManager)
        <section class="card falcon-card mb-4"><div class="card-body p-4">
            <span class="eyebrow">УПРАВЛЕНИЕ</span><h2 class="mt-2">Параметры задачи</h2>
            <form method="post" action="{{ route('tasks.update', $task) }}" enctype="multipart/form-data">@csrf @method('PUT')
                <label class="form-label">Название</label><input class="form-control mb-3" name="title" value="{{ $task->title }}" required>
                <label class="form-label">Описание</label><textarea class="form-control mb-3" name="description" rows="4">{{ $task->description }}</textarea>
                <label class="form-label">Приоритет</label><select class="form-select mb-3" name="priority">@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected($task->priority === $value)>{{ $label }}</option>@endforeach</select>
                <label class="form-label">Дедлайн</label><input class="form-control mb-3" type="date" name="due_date" value="{{ $task->due_date?->toDateString() }}">
                <label class="form-label">Метки</label>
                <div class="tag-picker mb-2">@foreach(\App\Models\Tag::orderBy('name')->get() as $tag)<input type="checkbox" name="tags[]" value="{{ $tag->id }}" id="et{{ $tag->id }}" @checked($task->tags->contains($tag))><label for="et{{ $tag->id }}" class="tag" style="background: {{ $tag->color }}">{{ $tag->name }}</label>@endforeach</div>
                <input class="form-control form-control-sm mb-3" name="new_tags" placeholder="Новые метки через запятую">
                <label class="form-label">Родительская задача</label>
                <select class="form-select mb-3" name="parent_id"><option value="">— нет —</option>@foreach(\App\Models\Task::whereKeyNot($task->id)->orderBy('title')->get(['id', 'title']) as $t)<option value="{{ $t->id }}" @selected($task->parent_id === $t->id)>{{ $t->title }}</option>@endforeach</select>
                <label class="form-label">Добавить пункты чек-листа</label><textarea class="form-control mb-3" name="checklist" rows="2" placeholder="Один пункт на строку"></textarea>
                <label class="form-label">Добавить материалы / скрины</label><input class="form-control mb-4" type="file" name="materials[]" multiple>
                <button class="btn btn-falcon w-100">Сохранить</button>
            </form>
        </div></section>
        <section class="card falcon-card"><div class="card-body p-4">
            <span class="eyebrow">ДОБАВИТЬ ИСПОЛНИТЕЛЯ</span>
            <form method="post" action="{{ route('tasks.assignments.store', $task) }}" class="mt-3">@csrf
                <select class="form-select mb-2" name="user_id" required>@foreach($employees->where('role', 'employee') as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
                <input class="form-control mb-2" name="scope" required placeholder="Что именно делает">
                <select class="form-select mb-3" name="reviewer_id"><option value="">Ревью: я</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">Ревью: {{ $employee->name }}</option>@endforeach</select>
                <button class="btn btn-outline-falcon w-100">Добавить</button>
            </form>
        </div></section>
    @elseif($mine)
        <section class="card falcon-card"><div class="card-body p-4">
            <span class="eyebrow">МОЙ УЧАСТОК</span><h2 class="mt-2">{{ $mine->scope }}</h2>
            <p class="mb-1">Статус: <b>{{ $states[$mine->status] }}</b></p>
            <p class="mb-1">Моё время: <b>{{ $hm($spent($mine)) }}</b></p>
            <p class="text-muted small mb-0">Остановите таймер и нажмите «Сдать на ревью». Если ревьюер вернёт работу — она снова появится у вас со статусом «Доработка».</p>
            <hr>
            <span class="eyebrow">МОЙ ЛОГ ВРЕМЕНИ</span>
            @foreach($mine->timeEntries->sortByDesc('started_at') as $entry)
                <div class="small d-flex justify-content-between"><span>{{ $entry->started_at->format('d.m H:i') }} – {{ $entry->stopped_at?->format('H:i') ?? 'идёт' }}</span><b>{{ $hm($entry->started_at->diffInSeconds($entry->stopped_at ?? now())) }}</b></div>
            @endforeach
        </div></section>
    @endif
</div>
</div>
@endsection
