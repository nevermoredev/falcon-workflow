@php
    $priorityNames = ['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий', 'urgent' => 'Срочно'];
    $checkTotal = $task->relationLoaded('checklist') ? $task->checklist->count() : 0;
    $checkDone = $checkTotal ? $task->checklist->where('is_completed', true)->count() : 0;
@endphp
<a href="{{ route('tasks.show', $task) }}" class="task-card pr-{{ $task->priority }} {{ $task->isOverdue() ? 'is-overdue' : '' }}">
    @if($task->tags->isNotEmpty())<div class="tags mb-1">@foreach($task->tags as $tag)<span class="tag" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</div>@endif
    <h3>{{ $task->title }}</h3>
    @isset($scope)<p class="scope-label">{{ $scope }}</p>@endisset
    <div class="foot">
        <span class="priority-label">{{ $priorityNames[$task->priority] }}</span>
        <span class="d-flex align-items-center gap-2">
            @if(($task->children_count ?? 0) > 0)<span title="Подзадачи">⑂ {{ $task->children_count }}</span>@endif
            @if($checkTotal)<span title="Чек-лист">☑ {{ $checkDone }}/{{ $checkTotal }}</span>@endif
            @if($task->due_date)<span class="{{ $task->isOverdue() ? 'overdue' : '' }}">⏱ {{ $task->due_date->format('d.m') }}</span>@endif
            @if($task->relationLoaded('assignments') && $task->assignments->isNotEmpty())
                <span class="avatars">@foreach($task->assignments->take(3) as $a)<span class="avatar sm" title="{{ $a->user->name }}">{{ mb_substr($a->user->name, 0, 1) }}</span>@endforeach</span>
            @endif
        </span>
    </div>
</a>
