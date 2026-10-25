<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Falcon Workflow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,600;8..144,700&display=swap" rel="stylesheet"><link href="{{ asset('css/app.css') }}?v=8" rel="stylesheet"></head>
<body class="login-page">
<div class="login-card">
    <div class="d-flex align-items-center gap-2 mb-4"><span class="brand-mark">➶</span><b>Falcon Workflow</b></div>
    <h1>Вход</h1><p class="text-muted">Задачи, время и качество работы команды.</p>
    @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('login.store') }}">@csrf
        <label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email') }}" placeholder="name@falcon.test" required autofocus>
        <label class="form-label mt-3">Пароль</label><input class="form-control" type="password" name="password" required>
        <button class="btn btn-falcon w-100 mt-4">Войти</button>
    </form>
    <hr>
    <small class="text-muted d-block lh-lg">Демо (пароль <b>password</b>):<br>
        PM: <b>manager@falcon.test</b><br>Тимлид: <b>lead@falcon.test</b><br>
        Дизайнер: <b>designer@falcon.test</b> · Фронт: <b>employee@falcon.test</b> · Бэк: <b>backend@falcon.test</b></small>
</div>
</body></html>
