@extends('layouts.app')
@section('content')
@php($statuses = ['todo' => 'К выполнению', 'in_progress' => 'В работе', 'review' => 'На ревью', 'done' => 'Готово'])
<div class="page-head">
    <div><span class="eyebrow">Все задачи</span><h1>Список задач</h1></div>
    @if(auth()->user()->isManager())<a class="btn btn-falcon" href="{{ route('tasks.create') }}">＋ Новая задача</a>@endif
</div>
@include('partials.filters', ['tags' => $tags, 'people' => $people, 'statuses' => $statuses])
<div class="card"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th class="ps-3">Задача</th><th>Метки</th><th>Приоритет</th><th>Статус</th><th>Исполнители</th><th>Поставил</th><th>Дедлайн</th></tr></thead><tbody>
    @forelse($tasks as $task)
        <tr class="pr-{{ $task->priority }}">
            <td class="ps-3"><a class="fw-semibold text-decoration-none" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                @if($task->parent)<br><small class="text-muted">↳ {{ $task->parent->title }}</small>@endif
                @if($task->children_count)<br><small class="text-muted">⑂ подзадач: {{ $task->children_count }}</small>@endif</td>
            <td>@foreach($task->tags as $tag)<span class="tag me-1" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</td>
            <td><span class="priority-label">{{ ['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий', 'urgent' => 'Срочно'][$task->priority] }}</span></td>
            <td><span class="task-status status-{{ $task->status }}">{{ $statuses[$task->status] }}</span></td>
            <td>{{ $task->assignments->pluck('user.name')->join(', ') ?: '—' }}</td>
            <td>{{ $task->assigner->name }}</td>
            <td class="{{ $task->isOverdue() ? 'overdue' : '' }}">{{ $task->due_date?->format('d.m.Y') ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-5">Ничего не найдено.</td></tr>
    @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $tasks->links('pagination::bootstrap-5') }}</div>
@endsection
