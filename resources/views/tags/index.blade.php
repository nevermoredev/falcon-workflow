@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">Справочник</span><h1>Метки</h1></div></div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card"><div class="table-responsive"><table class="table align-middle">
            <thead><tr><th class="ps-3">Метка</th><th>Задач</th><th></th></tr></thead><tbody>
            @forelse($tags as $tag)
                <tr>
                    <td class="ps-3"><a href="{{ route('tasks.index', ['tag' => $tag->id]) }}" class="tag text-decoration-none" style="background: {{ $tag->color }}">{{ $tag->name }}</a></td>
                    <td>{{ $tag->tasks_count }}</td>
                    <td class="text-end pe-3"><form method="post" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('Удалить метку?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger">Удалить</button></form></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-4">Меток нет.</td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4"><h2>Новая метка</h2>
            <form method="post" action="{{ route('tags.store') }}">@csrf
                <label class="form-label">Название</label><input class="form-control mb-2" name="name" required>
                <label class="form-label">Цвет</label><input class="form-control form-control-color mb-3 w-100" type="color" name="color" value="#6366f1">
                <button class="btn btn-falcon w-100">Создать</button>
            </form>
        </div>
    </div>
</div>
@endsection
