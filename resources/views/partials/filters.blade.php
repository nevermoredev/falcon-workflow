{{-- Общая строка фильтров: поиск, приоритет, метка, исполнитель, статус, просрочка --}}
<form class="filters" method="get">
    @foreach(($keep ?? []) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Поиск…">
    <select class="form-select" name="priority" onchange="this.form.submit()">
        <option value="">Любой приоритет</option>
        @foreach(['urgent' => 'Срочно', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий'] as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach
    </select>
    <select class="form-select" name="tag" onchange="this.form.submit()">
        <option value="">Все метки</option>
        @foreach($tags as $tag)<option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>{{ $tag->name }}</option>@endforeach
    </select>
    @isset($people)
        <select class="form-select" name="assignee" onchange="this.form.submit()">
            <option value="">Все исполнители</option>
            @foreach($people as $person)<option value="{{ $person->id }}" @selected(request('assignee') == $person->id)>{{ $person->name }}</option>@endforeach
        </select>
    @endisset
    @isset($statuses)
        <select class="form-select" name="status" onchange="this.form.submit()">
            <option value="">Любой статус</option>
            @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
        </select>
    @endisset
    <a class="chip {{ request('priority') === 'urgent' ? 'active' : '' }}" href="?{{ http_build_query(array_merge(request()->except('page'), ['priority' => request('priority') === 'urgent' ? null : 'urgent'])) }}">🔥 Срочные</a>
    <a class="chip {{ request()->boolean('overdue') ? 'active' : '' }}" href="?{{ http_build_query(array_merge(request()->except('page'), ['overdue' => request()->boolean('overdue') ? null : 1])) }}">⏱ Просроченные</a>
    @if(request()->hasAny(['q', 'priority', 'tag', 'assignee', 'status', 'overdue']))<a class="small text-muted" href="?{{ http_build_query($keep ?? []) }}">Сбросить</a>@endif
</form>
