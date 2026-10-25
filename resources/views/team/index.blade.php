@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">Команда</span><h1>Люди</h1></div></div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card"><div class="table-responsive"><table class="table align-middle">
            <thead><tr><th class="ps-3">Сотрудник</th><th>Должность</th><th>Роль</th><th>В работе</th><th>Выполнено</th><th></th></tr></thead><tbody>
            @foreach($people as $person)
                <tr>
                    <td class="ps-3"><div class="d-flex align-items-center gap-2"><span class="avatar">{{ mb_substr($person->name, 0, 1) }}</span><div><b>{{ $person->name }}</b><br><small class="text-muted">{{ $person->email }}</small></div></div></td>
                    <td>{{ $person->position ?? '—' }}</td>
                    <td><span class="task-status {{ $person->isManager() ? 'status-in_progress' : '' }}">{{ $person->isManager() ? 'менеджер' : 'сотрудник' }}</span></td>
                    <td>{{ $person->active_count }}</td>
                    <td>{{ $person->done_count }}</td>
                    <td>@unless($person->isManager())<a href="{{ route('reports.person', $person) }}">Отчёт →</a>@endunless</td>
                </tr>
            @endforeach
        </tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4">
            <h2>Добавить</h2>
            <form method="post" action="{{ route('team.store') }}">@csrf
                <label class="form-label">Имя</label><input class="form-control mb-2" name="name" required>
                <label class="form-label">Email</label><input class="form-control mb-2" type="email" name="email" required>
                <label class="form-label">Должность</label><input class="form-control mb-2" name="position" placeholder="Frontend-разработчик">
                <label class="form-label">Роль</label><select class="form-select mb-3" name="role"><option value="employee">Сотрудник</option><option value="manager">Менеджер</option></select>
                <button class="btn btn-falcon w-100">Добавить</button>
                <small class="text-muted d-block mt-2">Временный пароль: password</small>
            </form>
        </div>
    </div>
</div>
@endsection
