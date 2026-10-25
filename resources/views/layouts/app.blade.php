<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — задачи и время</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,600;8..144,700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v=8" rel="stylesheet">
</head>
<body>
@php
    $me = auth()->user();
    $nav = $me->isManager()
        ? [['dashboard', 'Доска', '▦'], ['tasks.index', 'Все задачи', '☰'], ['tasks.create', 'Новая задача', '＋'], ['team.index', 'Команда', '◎'], ['tags.index', 'Метки', '◆'], ['reports.index', 'Отчёты', '▤']]
        : [['dashboard', 'Мои задачи', '▦'], ['tasks.index', 'Все задачи', '☰']];
@endphp
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">➶</span><span>Falcon<small>Workflow</small></span></a>
        <nav class="side-nav">
            @foreach($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="{{ request()->routeIs($route === 'reports.index' ? 'reports.*' : $route) ? 'active' : '' }}"><span class="ico">{{ $icon }}</span>{{ $label }}</a>
            @endforeach
        </nav>
        @unless($me->isManager())
            @php($timerState = \App\Http\Controllers\TimeEntryController::state($me))
            <div class="side-timer {{ $timerState['running'] ? '' : 'd-none' }}" data-side-timer data-stop-url="{{ route('timer.stop') }}" data-current-url="{{ route('timer.current') }}" data-state='@json($timerState)'>
                <small>● Таймер идёт</small>
                <a data-task href="{{ $timerState['url'] ?? '#' }}">{{ $timerState['task'] ?? '' }}</a>
                <div class="d-flex align-items-center justify-content-between"><span class="timer-clock" data-clock>0:00:00</span><button type="button" class="btn btn-stop btn-sm" data-stop>■ Стоп</button></div>
            </div>
        @endunless
        <div class="side-user">
            <span class="avatar">{{ mb_substr($me->name, 0, 1) }}</span>
            <div><b>{{ $me->name }}</b><small>{{ $me->position ?? ($me->isManager() ? 'Менеджер' : 'Сотрудник') }}</small></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="Выйти">⎋</button></form>
        </div>
    </aside>
    <main class="page-shell">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/timer.js') }}?v=2"></script>
@stack('scripts')
</body></html>
