@extends('layouts.app')
@section('content')
@php
    $hm = fn ($s) => sprintf('%d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60));
    $ratingClass = fn ($r) => $r === null ? '' : ($r >= 75 ? 'rating-ok' : ($r >= 55 ? 'rating-mid' : 'rating-bad'));
    $tabs = ['overview' => 'Обзор', 'people' => 'Сотрудники', 'time' => 'Время', 'deadlines' => 'Дедлайны', 'quality' => 'Качество и ревью', 'tags' => 'По меткам'];
    $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $totalDone = $metrics->sum('done');
@endphp
<div class="page-head">
    <div><span class="eyebrow">Аналитика</span><h1>Отчёты</h1></div>
    <form class="filters mb-0"><input class="form-control" type="date" name="from" value="{{ $period['from'] }}"><input class="form-control" type="date" name="to" value="{{ $period['to'] }}"><button class="btn btn-falcon">Показать</button></form>
</div>
<div class="tabs">@foreach($tabs as $key => $label)<a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route('reports.index', ['tab' => $key] + $period) }}">{{ $label }}</a>@endforeach</div>

@if($tab === 'overview')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Создано задач</small><strong>{{ $created }}</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Закрыто задач</small><strong>{{ $doneTasks }}</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Часов в работе</small><strong>{{ $hm($entries->sum(fn ($e) => $e->started_at->diffInSeconds($e->stopped_at))) }}</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>В срок</small><strong>{{ $totalDone ? round($metrics->sum('on_time') / $totalDone * 100) : 0 }}%</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Принято с первого раза</small><strong>{{ $totalDone ? round($metrics->sum('clean') / $totalDone * 100) : 0 }}%</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Возвратов с ревью</small><strong>{{ $metrics->sum('reworks') }}</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Просрочено сейчас</small><strong class="{{ $openOverdue->count() ? 'overdue' : '' }}">{{ $openOverdue->count() }}</strong></div></div>
        <div class="col-6 col-lg-3"><div class="card kpi"><small>Открытых задач</small><strong>{{ $statusCounts->except('done')->sum() }}</strong></div></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-8"><div class="card p-4"><h2>По неделям</h2>
            @php($maxH = max(1, $weeks->max('hours')))
            <table class="table mt-2"><thead><tr><th>Неделя</th><th>Часы</th><th style="width: 50%"></th><th>Закрыто</th></tr></thead><tbody>
            @foreach($weeks as $w)<tr><td>{{ $w['label'] }}</td><td>{{ number_format($w['hours'], 1) }}</td><td><div class="bar"><i style="width: {{ $w['hours'] / $maxH * 100 }}%"></i></div></td><td>{{ $w['done'] }}</td></tr>@endforeach
            </tbody></table>
        </div></div>
        <div class="col-lg-4"><div class="card p-4"><h2>Просрочено сейчас</h2>
            @forelse($openOverdue as $task)<div class="py-2 border-bottom"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><br><small class="overdue">дедлайн {{ $task->due_date->format('d.m') }}</small> <small class="text-muted">· {{ $task->assignments->pluck('user.name')->join(', ') ?: 'без исполнителя' }}</small></div>@empty<p class="text-muted">Нет.</p>@endforelse
        </div></div>
    </div>

@elseif($tab === 'people')
    <div class="row g-3">
    @foreach($people as $person)@php($m = $metrics[$person->id])
        <div class="col-md-6 col-xl-4"><div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex gap-2 align-items-center"><span class="avatar">{{ mb_substr($person->name, 0, 1) }}</span><div><b>{{ $person->name }}</b><br><small class="text-muted">{{ $person->position }}</small></div></div>
                <div class="text-end"><span class="score-big {{ $ratingClass($m['rating']) }}">{{ $m['rating'] ?? '—' }}</span><br><small class="text-muted">рейтинг</small></div>
            </div>
            <table class="table table-sm mt-3">
                <tr><td>Время</td><td class="text-end">{{ $hm($m['seconds']) }}</td></tr>
                <tr><td>Принято задач</td><td class="text-end">{{ $m['done'] }}</td></tr>
                <tr><td>В срок</td><td class="text-end">{{ $m['on_time'] }} / {{ $m['done'] }}</td></tr>
                <tr><td>С первого раза</td><td class="text-end">{{ $m['clean'] }} / {{ $m['done'] }}</td></tr>
                <tr><td>Возвратов с ревью</td><td class="text-end {{ $m['reworks'] > 3 ? 'overdue' : '' }}">{{ $m['reworks'] }}</td></tr>
                <tr><td>Средняя оценка</td><td class="text-end">{{ $m['score'] ? number_format($m['score'], 1) : '—' }}</td></tr>
                <tr><td>Просрочено сейчас</td><td class="text-end {{ $m['overdue_open'] ? 'overdue' : '' }}">{{ $m['overdue_open'] }}</td></tr>
            </table>
            <a href="{{ route('reports.person', ['user' => $person] + $period) }}">Личный отчёт →</a>
        </div></div>
    @endforeach
    </div>
    <p class="small text-muted mt-3">Рейтинг: 40% — сдача в срок, 30% — приём с первого раза без возвратов, 30% — оценка ревьюера.</p>

@elseif($tab === 'time')
    <div class="card p-4 mb-4"><h2>Часы по неделям</h2><div class="table-responsive"><table class="table mt-2">
        <thead><tr><th>Сотрудник</th>@foreach($weeks as $w)<th>{{ $w->format('d.m') }}</th>@endforeach<th>Итого</th></tr></thead><tbody>
        @foreach($people as $person)<tr><td class="fw-semibold">{{ $person->name }}</td>@foreach($matrix[$person->id] as $s)<td style="background: rgba(79,70,229,{{ min(.6, $s / 3600 / 60) }})">{{ $s ? round($s / 3600) : '' }}</td>@endforeach<td class="fw-semibold">{{ $hm($matrix[$person->id]->sum()) }}</td></tr>@endforeach
    </tbody></table></div></div>
    <div class="card p-4 mb-4"><h2>Самые затратные задачи</h2><table class="table mt-2"><thead><tr><th>Задача</th><th>Кто</th><th>Время</th></tr></thead><tbody>
        @foreach($byTask as $row)<tr><td>@if($row['task'])<a href="{{ route('tasks.show', $row['task']) }}">{{ $row['title'] }}</a>@else{{ $row['title'] }}@endif</td><td>{{ $row['people'] }}</td><td>{{ $hm($row['seconds']) }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="card p-4"><h2>Лог времени</h2><div class="table-responsive" style="max-height: 500px"><table class="table mt-2"><thead><tr><th>Исполнитель</th><th>Задача</th><th>Поставил</th><th>Ревью</th><th>Начало</th><th>Затрачено</th></tr></thead><tbody>
        @foreach($entries->take(300) as $entry)<tr><td>{{ $entry->user->name }}</td><td>{{ $entry->task }}</td><td>{{ $entry->workTask?->assigner?->name ?? '—' }}</td><td>{{ $entry->assignment?->reviewer?->name ?? '—' }}</td><td>{{ $entry->started_at->format('d.m.Y H:i') }}</td><td>{{ $hm($entry->started_at->diffInSeconds($entry->stopped_at)) }}</td></tr>@endforeach
    </tbody></table></div></div>

@elseif($tab === 'deadlines')
    <div class="row g-3 mb-4">
    @foreach($people as $person)@php($m = $metrics[$person->id])
        <div class="col-md-6 col-xl-3"><div class="card kpi"><small>{{ $person->name }} · в срок</small><strong>{{ $m['done'] ? round($m['on_time'] / $m['done'] * 100) : '—' }}%</strong><div class="bar mt-1"><i style="width: {{ $m['done'] ? $m['on_time'] / $m['done'] * 100 : 0 }}%; background: var(--ok)"></i></div></div></div>
    @endforeach
    </div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card p-4"><h2>Сдано с опозданием</h2><table class="table mt-2"><thead><tr><th>Задача</th><th>Кто</th><th>Дедлайн</th><th>Опоздание</th></tr></thead><tbody>
            @forelse($late as $a)<tr><td><a href="{{ route('tasks.show', $a->task_id) }}">{{ $a->task->title }}</a></td><td>{{ $a->user->name }}</td><td>{{ $a->task->due_date->format('d.m') }}</td><td class="overdue">+{{ $a->task->due_date->diffInDays($a->completed_at) }} дн.</td></tr>@empty<tr><td colspan="4" class="text-muted">Нет.</td></tr>@endforelse
        </tbody></table></div></div>
        <div class="col-lg-6">
            <div class="card p-4 mb-4"><h2>Просрочено сейчас</h2><table class="table mt-2"><tbody>
                @forelse($openOverdue as $a)<tr><td><a href="{{ route('tasks.show', $a->task_id) }}">{{ $a->task->title }}</a></td><td>{{ $a->user->name }}</td><td class="overdue">{{ $a->task->due_date->format('d.m') }}</td></tr>@empty<tr><td class="text-muted">Нет.</td></tr>@endforelse
            </tbody></table></div>
            <div class="card p-4"><h2>Ближайшие 7 дней</h2><table class="table mt-2"><tbody>
                @forelse($upcoming as $task)<tr><td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td><td>{{ $task->assignments->pluck('user.name')->join(', ') ?: '—' }}</td><td>{{ $task->due_date->format('d.m') }}</td></tr>@empty<tr><td class="text-muted">Нет.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>

@elseif($tab === 'quality')
    <div class="card p-4 mb-4"><h2>Ревьюеры</h2><table class="table mt-2"><thead><tr><th>Ревьюер</th><th>Принято работ</th><th>Вернул на доработку</th><th>Средняя оценка</th><th>Время до ревью</th></tr></thead><tbody>
        @foreach($reviewers as $r)<tr><td class="fw-semibold">{{ $r['user']->name }} <small class="text-muted">{{ $r['user']->position }}</small></td><td>{{ $r['count'] }}</td><td>{{ $r['reworks'] }}</td><td>{{ $r['score'] ? number_format($r['score'], 1) : '—' }}</td><td>{{ $r['turnaround'] ? number_format($r['turnaround'], 1).' ч' : '—' }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="card p-4 mb-4"><h2>Исполнители</h2><table class="table mt-2"><thead><tr><th>Сотрудник</th><th>Принято</th><th>С первого раза</th><th>Возвратов</th><th>Возвратов на задачу</th><th>Оценка</th></tr></thead><tbody>
        @foreach($people as $person)@php($m = $metrics[$person->id])<tr><td class="fw-semibold">{{ $person->name }}</td><td>{{ $m['done'] }}</td><td>{{ $m['done'] ? round($m['clean'] / $m['done'] * 100) : 0 }}%</td><td>{{ $m['reworks'] }}</td><td class="{{ $m['done'] && $m['reworks'] / $m['done'] > .5 ? 'overdue' : '' }}">{{ $m['done'] ? number_format($m['reworks'] / $m['done'], 2) : '—' }}</td><td>{{ $m['score'] ? number_format($m['score'], 1) : '—' }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="card p-4"><h2>Больше всего возвратов</h2><table class="table mt-2"><thead><tr><th>Задача</th><th>Исполнитель</th><th>Ревьюер</th><th>Возвратов</th><th>Последний комментарий ревью</th></tr></thead><tbody>
        @forelse($worst as $a)<tr><td><a href="{{ route('tasks.show', $a->task_id) }}">{{ $a->task->title }}</a></td><td>{{ $a->user->name }}</td><td>{{ $a->reviewer?->name }}</td><td class="overdue">{{ $a->rework_count }}</td><td class="small">{{ Str::limit($a->review_comment, 90) }}</td></tr>@empty<tr><td colspan="5" class="text-muted">Нет.</td></tr>@endforelse
    </tbody></table></div>

@elseif($tab === 'tags')
    @php($maxS = max(1, $tagRows->max('seconds')))
    <div class="card p-4"><table class="table"><thead><tr><th>Метка</th><th>Задач</th><th>Готово</th><th>Просрочено</th><th>Возвратов</th><th>Часы</th><th style="width: 30%"></th></tr></thead><tbody>
        @foreach($tagRows as $row)<tr><td><a class="tag text-decoration-none" href="{{ route('tasks.index', ['tag' => $row['tag']->id]) }}" style="background: {{ $row['tag']->color }}">{{ $row['tag']->name }}</a></td><td>{{ $row['total'] }}</td><td>{{ $row['done'] }}</td><td class="{{ $row['overdue'] ? 'overdue' : '' }}">{{ $row['overdue'] }}</td><td>{{ $row['reworks'] }}</td><td>{{ $hm($row['seconds']) }}</td><td><div class="bar"><i style="width: {{ $row['seconds'] / $maxS * 100 }}%; background: {{ $row['tag']->color }}"></i></div></td></tr>@endforeach
    </tbody></table></div>
@endif
@endsection
