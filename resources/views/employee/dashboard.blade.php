@extends('layouts.app')
@section('content')
@php
    $states = ['todo' => 'К выполнению', 'in_progress' => 'В работе', 'submitted' => 'На ревью', 'rework' => 'Доработка'];
    $hm = fn ($s) => sprintf('%d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));
    $urgent = $assignments->filter(fn ($a) => $a->task->priority === 'urgent' || $a->task->isOverdue() || $a->status === 'rework');
    $tabs = ['active' => ['Мои задачи', $assignments->count()], 'queue' => ['Очередь', $queue->count()], 'reviews' => ['На моём ревью', $reviews->count()], 'history' => ['История', $history->count()]];
@endphp
<div class="page-head">
    <div><span class="eyebrow">Рабочий фокус</span><h1>Привет, {{ explode(' ', auth()->user()->name)[0] }}</h1><p class="lead mb-0">За эту неделю: <b>{{ $hm($weekSeconds) }}</b> ч</p></div>
</div>

@if($urgent->isNotEmpty() && $tab === 'active')
    <div class="card p-3 mb-3" style="border-color:#fecdd3;background:#fff1f2">
        <b class="text-danger">Требуют внимания: {{ $urgent->count() }}</b>
        <div class="small mt-1">@foreach($urgent as $a)<a class="me-3" href="{{ route('tasks.show', $a->task) }}">{{ $a->task->title }}</a>@endforeach</div>
    </div>
@endif

<div class="tabs">@foreach($tabs as $key => [$label, $count])<a class="{{ $tab === $key ? 'active' : '' }}" href="?{{ http_build_query(array_merge(request()->except('tab'), ['tab' => $key])) }}">{{ $label }}<span class="count">{{ $count }}</span></a>@endforeach</div>
@include('partials.filters', ['tags' => $tags, 'keep' => ['tab' => $tab]])

@if($tab === 'active')
    <div class="row g-3">
    @forelse($assignments as $assignment)
        <div class="col-md-6 col-xl-4">
            @include('partials.task-card', ['task' => $assignment->task, 'scope' => 'Мой участок: '.$assignment->scope])
            <div class="d-flex justify-content-between small px-1 mb-2"><span class="task-status status-{{ $assignment->status }}">{{ $states[$assignment->status] }}</span><span class="text-muted">⌛ {{ $hm($assignment->timeEntries->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at ?? now()))) }} · ревью: {{ $assignment->reviewer?->name ?? '—' }}</span></div>
        </div>
    @empty
        <div class="col"><div class="empty-box">Нет активных задач — загляните в очередь.</div></div>
    @endforelse
    </div>
@elseif($tab === 'queue')
    <div class="row g-3">
    @forelse($queue as $task)
        <div class="col-md-6 col-xl-4">
            @include('partials.task-card', ['task' => $task])
            <form method="post" action="{{ route('tasks.claim', $task) }}" class="mb-2">@csrf<button class="btn btn-falcon btn-sm w-100">Взять себе</button></form>
        </div>
    @empty
        <div class="col"><div class="empty-box">Свободных задач нет.</div></div>
    @endforelse
    </div>
@elseif($tab === 'reviews')
    <div class="row g-3">
    @forelse($reviews as $assignment)
        <div class="col-md-6 col-xl-4">@include('partials.task-card', ['task' => $assignment->task, 'scope' => $assignment->user->name.': '.$assignment->scope])</div>
    @empty
        <div class="col"><div class="empty-box">Нечего проверять.</div></div>
    @endforelse
    </div>
@else
    <div class="card"><div class="table-responsive"><table class="table align-middle">
        <thead><tr><th class="ps-3">Задача</th><th>Поставил</th><th>Ревью</th><th>Дедлайн</th><th>Принято</th><th>Время</th><th>Возвраты</th><th>Оценка</th></tr></thead><tbody>
        @forelse($history as $a)
            @php($late = $a->task->due_date && $a->completed_at->gt($a->task->due_date->copy()->endOfDay()))
            <tr><td class="ps-3"><a href="{{ route('tasks.show', $a->task) }}">{{ $a->task->title }}</a><br><small class="text-muted">{{ $a->scope }}</small></td><td>{{ $a->task->assigner->name }}</td><td>{{ $a->reviewer?->name ?? '—' }}</td><td>{{ $a->task->due_date?->format('d.m.Y') ?? '—' }}</td><td class="{{ $late ? 'overdue' : '' }}">{{ $a->completed_at->format('d.m.Y') }}</td><td>{{ $hm($a->timeEntries->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at ?? now()))) }}</td><td class="{{ $a->rework_count ? 'overdue' : '' }}">{{ $a->rework_count }}</td><td>{{ $a->score ? $a->score.'/5' : '—' }}</td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Пока пусто.</td></tr>
        @endforelse
    </tbody></table></div></div>
@endif
@endsection
