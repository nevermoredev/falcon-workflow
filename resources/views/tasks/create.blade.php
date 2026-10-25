@extends('layouts.app')
@section('content')
<div class="page-head"><div><a href="{{ route('dashboard') }}" class="back-link">← Доска</a><h1>Новая задача</h1></div></div>
<form method="post" action="{{ route('tasks.store') }}" enctype="multipart/form-data" class="editor">@csrf
    <div class="card p-4">
        <input class="form-control title-input" name="title" value="{{ old('title') }}" placeholder="Название задачи" required autofocus>

        <div class="editor-section">
            <span class="eyebrow">Метки</span>
            <div class="tag-picker">
                @foreach($tags as $tag)
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" id="tag{{ $tag->id }}" @checked(in_array($tag->id, old('tags', [])))>
                    <label for="tag{{ $tag->id }}" class="tag" style="background: {{ $tag->color }}; color: #fff">{{ $tag->name }}</label>
                @endforeach
            </div>
            <input class="form-control form-control-sm mt-2" name="new_tags" value="{{ old('new_tags') }}" placeholder="Новые метки через запятую">
        </div>

        <div class="editor-section">
            <span class="eyebrow">Описание</span>
            <textarea class="form-control" rows="6" name="description" placeholder="Контекст, что должно получиться, критерии приёмки, ссылки">{{ old('description') }}</textarea>
        </div>

        <div class="editor-section">
            <span class="eyebrow">Чек-лист</span>
            <div id="checklist">
                <div class="check-edit"><input class="form-control form-control-sm" name="checklist_items[]" placeholder="Пункт"><button type="button" class="btn btn-outline-falcon btn-sm" onclick="this.parentNode.remove()">✕</button></div>
            </div>
            <button type="button" class="btn btn-outline-falcon btn-sm" id="add-check">＋ Пункт</button>
        </div>

        <div class="editor-section">
            <span class="eyebrow">Исполнители и их участки</span>
            <p class="small text-muted mb-2">Отметьте одного или нескольких. Никого не отметили — задача уйдёт в общую очередь.</p>
            @foreach($people->where('role', 'employee') as $person)
                <label class="member-row">
                    <input class="form-check-input mt-0" type="checkbox" name="assignees[]" value="{{ $person->id }}">
                    <span class="avatar sm">{{ mb_substr($person->name, 0, 1) }}</span>
                    <span style="min-width: 170px">{{ $person->name }}<br><small class="text-muted">{{ $person->position }}</small></span>
                    <input type="text" class="form-control form-control-sm" name="scopes[{{ $person->id }}]" placeholder="Что делает">
                </label>
            @endforeach
        </div>

        <div class="editor-section">
            <span class="eyebrow">Материалы и скриншоты</span>
            <label class="dropzone"><input type="file" name="materials[]" multiple hidden id="files">📎 Нажмите, чтобы прикрепить файлы<div class="small mt-1" id="file-names"></div></label>
        </div>
    </div>

    <div class="side-panel">
        <div class="card p-3">
            <div class="side-group">
                <span class="eyebrow d-block mb-2">Приоритет</span>
                <div class="seg">
                    @foreach(['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий', 'urgent' => '🔥'] as $value => $label)
                        <input type="radio" name="priority" value="{{ $value }}" id="p{{ $value }}" @checked(old('priority', 'medium') === $value)><label for="p{{ $value }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="side-group"><span class="eyebrow d-block mb-2">Дедлайн</span><input class="form-control" type="date" name="due_date" value="{{ old('due_date') }}"></div>
            <div class="side-group"><span class="eyebrow d-block mb-2">Ревьюер</span>
                <select class="form-select" name="reviewer_id"><option value="">Я</option>@foreach($people as $person)<option value="{{ $person->id }}">{{ $person->name }} · {{ $person->position }}</option>@endforeach</select>
            </div>
            <div class="side-group"><span class="eyebrow d-block mb-2">Родительская задача</span>
                <select class="form-select" name="parent_id"><option value="">— нет —</option>@foreach($tasks as $t)<option value="{{ $t->id }}" @selected($parentId === $t->id)>{{ $t->title }}</option>@endforeach</select>
            </div>
            <div class="side-group"><span class="eyebrow d-block mb-2">Дочерние задачи</span>
                <select class="form-select" name="children[]" multiple size="5">@foreach($tasks as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach</select>
                <small class="text-muted">Ctrl+клик — несколько</small>
            </div>
            <button class="btn btn-falcon w-100">Создать задачу</button>
        </div>
    </div>
</form>
@push('scripts')
<script>
document.getElementById('add-check').onclick = () => {
    const row = document.querySelector('#checklist .check-edit')?.cloneNode(true) ?? null;
    const box = document.getElementById('checklist');
    if (row) { row.querySelector('input').value = ''; box.appendChild(row); row.querySelector('input').focus(); }
    else box.insertAdjacentHTML('beforeend', '<div class="check-edit"><input class="form-control form-control-sm" name="checklist_items[]" placeholder="Пункт"><button type="button" class="btn btn-outline-falcon btn-sm" onclick="this.parentNode.remove()">✕</button></div>');
};
document.getElementById('checklist').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); document.getElementById('add-check').click(); } });
document.getElementById('files').onchange = e => document.getElementById('file-names').textContent = [...e.target.files].map(f => f.name).join(', ');
</script>
@endpush
@endsection
