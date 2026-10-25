@extends('layouts.app')
@section('content')
@php
    $hm = fn ($s) => sprintf('%d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));
    $states = ['todo' => 'К выполнению', 'in_progress' => 'В работе', 'submitted' => 'На ревью', 'rework' => 'Доработка', 'done' => 'Принято'];
    $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp
<div class="page-head">
    <div><a class="back-link" href="{{ route('reports.index', ['tab' => 'people'] + $period) }}">← Сотрудники</a>
        <div class="d-flex align-items-center gap-3 mt-2"><span class="avatar" style="width: 52px; height: 52px; font-size: 20px">{{ mb_substr($user->name, 0, 1) }}</span><div><h1 class="mb-0">{{ $user->name }}</h1><span class="text-muted">{{ $user->position }}</span></div></div></div>
    <form class="filters mb-0"><input class="form-control" type="date" name="from" value="{{ $period['from'] }}"><input class="form-control" type="date" name="to" value="{{ $period['to'] }}"><button class="btn btn-falcon">Показать</button></form>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2"><div class="card kpi"><small>Рейтинг</small><strong>{{ $m['rating'] ?? '—' }}</strong></div></div>
    <div class="col-6 col-lg-2"><div class="card kpi"><small>Время</small><strong>{{ $hm($m['seconds']) }}</strong></div></div>
    <div class="col-6 col-lg-2"><div class="card kpi"><small>Принято</small><strong>{{ $m['done'] }}</strong></div></div>
    <div class="col-6 col-lg-2"><div class="card kpi"><small>В срок</small><strong>{{ $m['done'] ? round($m['on_time'] / $m['done'] * 100) : 0 }}%</strong></div></div>
    <div class="col-6 col-lg-2"><div class="card kpi"><small>Возвратов</small><strong>{{ $m['reworks'] }}</strong></div></div>
    <div class="col-6 col-lg-2"><div class="card kpi"><small>Оценка</small><strong>{{ $m['score'] ? number_format($m['score'], 1) : '—' }}</strong></div></div>
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card p-4 h-100"><h2>Часы по неделям</h2>
        @php($max = max(1, $weeks->max('hours')))
        @foreach($weeks as $w)<div class="d-flex align-items-center gap-2 small mb-1"><span style="width: 44px">{{ $w['label'] }}</span><div class="bar flex-grow-1"><i style="width: {{ $w['hours'] / $max * 100 }}%"></i></div><span style="width: 40px" class="text-end">{{ number_format($w['hours'], 1) }}</span></div>@endforeach
    </div></div>
    <div class="col-lg-6"><div class="card p-4 h-100"><h2>Сейчас в работе</h2>
        @forelse($active as $a)<div class="py-2 border-bottom d-flex justify-content-between"><span><a href="{{ route('tasks.show', $a->task_id) }}">{{ $a->task->title }}</a><br><small class="text-muted">{{ $a->scope }}</small></span><span><span class="task-status status-{{ $a->status }}">{{ $states[$a->status] }}</span> @if($a->task->isOverdue())<small class="overdue">просрочено</small>@endif</span></div>@empty<p class="text-muted">Нет.</p>@endforelse
        <h2 class="mt-4">Последние замечания ревью</h2>
        @forelse($feedback as $a)<div class="small py-1 border-bottom"><b>{{ $a->task->title }}</b>: {{ $a->review_comment }}</div>@empty<p class="text-muted">Нет.</p>@endforelse
    </div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th class="ps-3">Задача</th><th>Метки</th><th>Поставил</th><th>Ревью</th><th>Дедлайн</th><th>Принято</th><th>Время</th><th>Возвраты</th><th>Оценка</th></tr></thead><tbody>
    @forelse($finished as $a)@php($late = $a->task->due_date && $a->completed_at->gt($a->task->due_date->copy()->endOfDay()))
        <tr><td class="ps-3"><a href="{{ route('tasks.show', $a->task_id) }}">{{ $a->task->title }}</a><br><small class="text-muted">{{ $a->scope }}</small></td><td>@foreach($a->task->tags as $tag)<span class="tag me-1" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</td><td>{{ $a->task->assigner->name }}</td><td>{{ $a->reviewer?->name ?? '—' }}</td><td>{{ $a->task->due_date?->format('d.m') ?? '—' }}</td><td class="{{ $late ? 'overdue' : '' }}">{{ $a->completed_at->format('d.m') }}</td><td>{{ $hm($a->timeEntries->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at ?? now()))) }}</td><td class="{{ $a->rework_count ? 'overdue' : '' }}">{{ $a->rework_count }}</td><td>{{ $a->score ?? '—' }}</td></tr>
    @empty<tr><td colspan="9" class="text-center text-muted py-4">Нет принятых задач за период.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
