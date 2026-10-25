@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">Доска команды</span><h1>Задачи</h1></div>
    <a class="btn btn-falcon" href="{{ route('tasks.create') }}">＋ Новая задача</a>
</div>
@include('partials.filters', ['tags' => $tags, 'people' => $people])
<div class="kanban">
@foreach(['todo' => 'К выполнению', 'in_progress' => 'В работе', 'review' => 'На ревью', 'done' => 'Готово · 14 дней'] as $status => $label)
    <section class="kanban-column">
        <div class="kanban-title">{{ $label }} <span>{{ $tasks->where('status', $status)->count() }}</span></div>
        @forelse($tasks->where('status', $status) as $task)
            @include('partials.task-card', ['task' => $task])
        @empty
            <div class="kanban-empty">Пусто</div>
        @endforelse
    </section>
@endforeach
</div>
@endsection
