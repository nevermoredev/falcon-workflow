<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-история за 3 месяца: команда делает приложение «Блокнот» (заметки с синхронизацией).
 * PM ставит задачи и принимает дизайн, тимлид ревьюит разработчиков.
 * У исполнителей есть типичные косяки: возвраты с ревью, сорванные дедлайны.
 */
class DatabaseSeeder extends Seeder
{
    private Carbon $start;
    private array $users = [];
    private array $tags = [];

    public function run(): void
    {
        mt_srand(42);
        $this->start = now()->startOfDay()->subDays(91);

        foreach ([
            'pm' => ['manager@falcon.test', 'Анна Волкова', 'manager', 'Project Manager'],
            'lead' => ['lead@falcon.test', 'Дмитрий Козлов', 'employee', 'Тимлид разработки'],
            'design' => ['designer@falcon.test', 'Мария Орлова', 'employee', 'UI/UX-дизайнер'],
            'front' => ['employee@falcon.test', 'Илья Смирнов', 'employee', 'Frontend-разработчик'],
            'back' => ['backend@falcon.test', 'Олег Петров', 'employee', 'Backend-разработчик'],
        ] as $key => [$email, $name, $role, $position]) {
            $this->users[$key] = User::updateOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'position' => $position, 'password' => Hash::make('password')]);
        }

        foreach (['дизайн' => '#ec4899', 'ux' => '#d946ef', 'frontend' => '#0ea5e9', 'backend' => '#10b981', 'баг' => '#ef4444', 'исследование' => '#8b5cf6', 'инфраструктура' => '#64748b', 'релиз' => '#f59e0b', 'мобайл' => '#14b8a6'] as $name => $color) {
            $this->tags[$name] = Tag::updateOrCreate(['name' => $name], ['color' => $color]);
        }

        foreach ($this->plan() as $epic) {
            $parent = $this->task(null, $epic['title'], $epic['description'], $epic['tags'], 'high', $epic['day'], $epic['due']);
            foreach ($epic['tasks'] as $def) $this->child($parent, $def);
            $this->syncEpic($parent);
        }
    }

    /** Эпики и задачи. [название, метки, приоритет, исполнитель, участок, день старта, план дней, факт дней|статус, возвраты, оценка, замечания ревью] */
    private function plan(): array
    {
        return [
            ['title' => 'Исследование и концепция «Блокнота»', 'description' => "Понять, чем наш блокнот лучше Google Keep и Apple Notes.\nРезультат: портрет пользователя, список функций MVP, карта экранов.", 'tags' => ['исследование', 'ux'], 'day' => 0, 'due' => 14, 'tasks' => [
                ['Интервью с 8 пользователями заметок', ['исследование', 'ux'], 'high', 'design', 'Провести интервью и собрать инсайты', 0, 6, 7, 0, 5, []],
                ['Конкурентный анализ: Keep, Notion, Apple Notes', ['исследование'], 'medium', 'design', 'Таблица сравнения функций', 1, 4, 4, 0, 4, []],
                ['Выбор стека и архитектура API', ['backend', 'инфраструктура'], 'high', 'back', 'ADR: Laravel API + PostgreSQL, схема синхронизации', 2, 5, 5, 0, 5, []],
                ['Каркас фронтенда: Vite + Vue, роутинг', ['frontend', 'инфраструктура'], 'medium', 'front', 'Скелет SPA, линтеры, CI-сборка', 3, 4, 6, 1, 3, ['CI не проходит: забыт eslint в зависимостях, сборка падает на чистом клоне.']],
                ['Карта экранов и user flow', ['ux', 'дизайн'], 'high', 'design', 'Flow: список → заметка → поиск → настройки', 7, 5, 6, 1, 4, ['Нет сценария восстановления удалённой заметки — добавь корзину во flow.']],
            ]],
            ['title' => 'Дизайн-система и макеты MVP', 'description' => 'Цвета, типографика, компоненты, макеты всех экранов MVP в Figma.', 'tags' => ['дизайн'], 'day' => 12, 'due' => 32, 'tasks' => [
                ['UI-kit: цвета, шрифты, кнопки, поля', ['дизайн'], 'high', 'design', 'Базовые компоненты в Figma', 12, 7, 9, 1, 4, ['Контраст серого текста не проходит AA, кнопки разной высоты в разных фреймах.']],
                ['Макет списка заметок и карточки', ['дизайн', 'ux'], 'high', 'design', 'Экран списка, состояния пустой/загрузка/ошибка', 16, 6, 8, 2, 3, ['Не нарисованы пустое состояние и состояние ошибки.', 'Пустое состояние есть, но карточка заметки не адаптирована под длинные заголовки.']],
                ['Макет редактора заметки', ['дизайн'], 'urgent', 'design', 'Редактор: форматирование, чек-листы, вложения', 21, 6, 10, 1, 4, ['Панель форматирования перекрывает текст на узких экранах.']],
                ['Тёмная тема в UI-kit', ['дизайн'], 'medium', 'design', 'Токены тёмной темы', 30, 5, 5, 0, 5, []],
                ['Иконки и иллюстрации пустых состояний', ['дизайн'], 'low', 'design', 'Набор из 24 иконок и 3 иллюстраций', 34, 6, 7, 0, 4, []],
            ]],
            ['title' => 'MVP: заметки и редактор', 'description' => "Создание, редактирование, удаление заметок. Редактор с форматированием.\nКритерий: можно вести заметки без потери данных.", 'tags' => ['frontend', 'backend'], 'day' => 20, 'due' => 55, 'tasks' => [
                ['API: CRUD заметок', ['backend'], 'high', 'back', 'REST-эндпоинты, валидация, тесты', 20, 6, 6, 0, 5, []],
                ['Авторизация: регистрация и вход', ['backend'], 'high', 'back', 'Sanctum-токены, сброс пароля', 24, 5, 7, 1, 4, ['Нет ограничения попыток входа — добавь throttle.']],
                ['Экран списка заметок', ['frontend'], 'high', 'front', 'Список, пагинация, пустое состояние по макету', 28, 5, 9, 2, 3, ['Не соответствует макету: отступы и шрифты другие, нет пустого состояния.', 'Бесконечная прокрутка дублирует заметки при быстром скролле.']],
                ['Редактор заметки (Tiptap)', ['frontend'], 'urgent', 'front', 'Форматирование, чек-листы, автосохранение', 33, 8, 14, 3, 2, ['Автосохранение срабатывает на каждый символ — 50 запросов в секунду.', 'Чек-листы теряют состояние после перезагрузки.', 'Курсор прыгает в начало при автосохранении.']],
                ['Корзина и восстановление заметок', ['backend', 'frontend'], 'medium', 'back', 'Soft delete + очистка через 30 дней', 40, 4, 4, 0, 5, []],
                ['Баг: заметка пропадает при двойном клике «Сохранить»', ['баг', 'frontend'], 'urgent', 'front', 'Найти и починить гонку запросов', 47, 1, 2, 1, 3, ['Починил только кнопку, при Ctrl+S баг остаётся.']],
            ]],
            ['title' => 'Синхронизация и офлайн', 'description' => 'Заметки доступны офлайн и синхронизируются между устройствами без конфликтов.', 'tags' => ['backend', 'frontend'], 'day' => 45, 'due' => 78, 'tasks' => [
                ['Протокол синхронизации с версиями', ['backend'], 'urgent', 'back', 'Дельты, версии, разрешение конфликтов', 45, 8, 16, 2, 3, ['Конфликт двух устройств затирает изменения без предупреждения.', 'Нет тестов на конфликты, миграция ломает старые заметки.']],
                ['Офлайн-хранилище в IndexedDB', ['frontend'], 'high', 'front', 'Кеш заметок, очередь изменений', 50, 7, 11, 2, 3, ['Очередь изменений теряется при закрытии вкладки.', 'При переполнении хранилища приложение падает без сообщения.']],
                ['Индикатор статуса синхронизации', ['frontend', 'ux'], 'medium', 'front', 'Иконка: синхронизировано / в процессе / ошибка', 60, 3, 3, 0, 4, []],
                ['Макет конфликта версий', ['дизайн', 'ux'], 'high', 'design', 'Диалог выбора версии заметки', 52, 4, 4, 0, 5, []],
                ['Ревью архитектуры синхронизации', ['backend'], 'high', 'lead', 'Проверить протокол и нагрузку', 62, 3, 3, 0, 5, []],
            ]],
            ['title' => 'Поиск, теги и папки', 'description' => 'Быстрый полнотекстовый поиск, теги и папки для организации заметок.', 'tags' => ['frontend', 'backend', 'ux'], 'day' => 64, 'due' => 92, 'tasks' => [
                ['Полнотекстовый поиск (PostgreSQL FTS)', ['backend'], 'high', 'back', 'Индекс, морфология русского языка', 64, 6, 7, 1, 4, ['Поиск не находит слова с «ё».']],
                ['Макет поиска и фильтров', ['дизайн', 'ux'], 'medium', 'design', 'Строка поиска, подсветка, фильтр по тегам', 64, 5, 7, 1, 4, ['Фильтры не помещаются на мобильном — нужна шторка.']],
                ['UI поиска с подсветкой', ['frontend'], 'high', 'front', 'Поиск с debounce и подсветкой совпадений', 72, 5, 8, 2, 3, ['Подсветка ломает HTML-разметку заметки (XSS!).', 'Debounce не работает, запрос на каждый символ.']],
                ['Теги: API и UI', ['backend', 'frontend'], 'medium', 'back', 'Модель тегов, автодополнение', 74, 5, 5, 0, 5, []],
                ['Папки и drag-and-drop', ['frontend'], 'medium', 'front', 'Дерево папок, перенос заметок мышью', 80, 6, 'submitted', 0, null, []],
                ['Иконки папок и тегов', ['дизайн'], 'low', 'design', '12 иконок для папок', 82, 4, 'submitted', 0, null, []],
            ]],
            ['title' => 'Релиз 1.0', 'description' => "Подготовка к публичному запуску.\nЧек: тесты, мониторинг, лендинг, сторы.", 'tags' => ['релиз'], 'day' => 78, 'due' => 98, 'tasks' => [
                ['Мониторинг ошибок (Sentry) и алерты', ['инфраструктура', 'релиз'], 'high', 'back', 'Sentry на фронте и бэке, алерты в Telegram', 78, 3, 4, 0, 5, []],
                ['Лендинг блокнота', ['дизайн', 'релиз'], 'high', 'design', 'Макет лендинга и скриншоты для сторов', 80, 6, 'rework', 1, null, ['Скриншоты сделаны со старой версией UI, без тёмной темы.']],
                ['Нагрузочное тестирование синхронизации', ['backend', 'релиз'], 'urgent', 'back', 'k6: 1000 устройств одновременно', 84, 3, 'in_progress', 0, null, []],
                ['Баг: тёмная тема — белые вспышки при загрузке', ['баг', 'frontend'], 'urgent', 'front', 'Убрать мигание при старте', 85, 2, 'rework', 1, null, ['Мигание осталось в Safari.']],
                ['Онбординг для новых пользователей', ['ux', 'frontend'], 'medium', 'front', '3 экрана приветствия', 88, 5, 'todo', 0, null, []],
                ['Чек-лист релиза и changelog', ['релиз'], 'medium', 'lead', 'Пройти чек-лист, собрать changelog', 89, 4, 'in_progress', 0, null, []],
            ]],
            ['title' => 'Мобильная адаптация (бэклог)', 'description' => 'PWA и адаптация под телефоны.', 'tags' => ['мобайл'], 'day' => 86, 'due' => 120, 'tasks' => [
                ['PWA: манифест и установка на телефон', ['мобайл', 'frontend'], 'medium', null, null, 86, 10, 'queue', 0, null, []],
                ['Жесты: свайп для удаления заметки', ['мобайл', 'ux'], 'low', null, null, 87, 14, 'queue', 0, null, []],
                ['Push-напоминания по заметкам', ['мобайл', 'backend'], 'high', null, null, 88, 12, 'queue', 0, null, []],
            ]],
        ];
    }

    private function task(?Task $parent, string $title, string $description, array $tags, string $priority, int $day, int $due): Task
    {
        $created = $this->start->copy()->addDays($day)->setTime(10, mt_rand(0, 59));
        $task = Task::create(['parent_id' => $parent?->id, 'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => 'todo', 'assigned_by' => $this->users['pm']->id, 'due_date' => $this->start->copy()->addDays($due)]);
        $task->forceFill(['created_at' => $created, 'updated_at' => $created])->save();
        $task->tags()->sync(collect($tags)->map(fn ($t) => $this->tags[$t]->id));
        return $task;
    }

    private function child(Task $parent, array $def): void
    {
        [$title, $tags, $priority, $who, $scope, $day, $plan, $actual, $reworks, $score, $notes] = $def;
        $task = $this->task($parent, $title, $this->describe($title, $scope), $tags, $priority, $day, $day + $plan);
        foreach (['Согласовать подход', 'Сделать основную часть', 'Проверить крайние случаи', 'Обновить документацию'] as $i => $item) {
            $task->checklist()->create(['title' => $item, 'position' => $i, 'is_completed' => is_int($actual) || ($i < 2 && $actual !== 'todo')]);
        }
        if ($actual === 'queue') return;

        $user = $this->users[$who];
        $reviewer = in_array($who, ['front', 'back']) ? $this->users['lead'] : $this->users['pm'];
        $begin = $task->created_at->copy()->addHours(2);
        $end = is_int($actual) ? $this->start->copy()->addDays($day + $actual)->setTime(mt_rand(15, 18), mt_rand(0, 59)) : now()->subHours(mt_rand(1, 20));
        if ($end->isFuture()) $end = now()->subHours(2);

        $status = is_int($actual) ? 'done' : $actual;
        $assignment = TaskAssignment::create([
            'task_id' => $task->id, 'user_id' => $user->id, 'reviewer_id' => $reviewer->id, 'scope' => $scope, 'status' => $status,
            'rework_count' => $reworks, 'score' => $status === 'done' ? $score : null,
            'review_comment' => $notes ? end($notes) : null,
            'submitted_at' => in_array($status, ['done', 'submitted', 'rework']) ? $end->copy()->subHours(5) : null,
            'reviewed_at' => in_array($status, ['done', 'rework']) ? $end->copy() : null,
            'completed_at' => $status === 'done' ? $end : null,
        ]);
        $assignment->forceFill(['created_at' => $begin])->save();
        $task->update(['assigned_to' => $user->id]);

        if ($status !== 'todo') $this->logTime($task, $assignment, $user, $begin, $end);
        $this->discuss($task, $assignment, $user, $reviewer, $begin, $end, $notes, $reworks);

        $taskStatus = ['done' => 'done', 'submitted' => 'review', 'rework' => 'in_progress', 'in_progress' => 'in_progress', 'todo' => 'todo'][$status];
        $task->forceFill(['status' => $taskStatus, 'completed_at' => $status === 'done' ? $end : null, 'updated_at' => $end])->save();
    }

    /** Рабочие интервалы по будням: 1–3 отрезка в день, 1–3 часа каждый. */
    private function logTime(Task $task, TaskAssignment $assignment, User $user, Carbon $from, Carbon $to): void
    {
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            if ($day->isWeekend()) continue;
            $cursor = $day->copy()->setTime(mt_rand(9, 11), mt_rand(0, 59));
            foreach (range(1, mt_rand(1, 3)) as $_) {
                $stop = $cursor->copy()->addMinutes(mt_rand(50, 170));
                if ($cursor->lt($from) || $stop->gt($to)) break;
                $user->timeEntries()->create(['task_id' => $task->id, 'assignment_id' => $assignment->id, 'task' => $task->title, 'started_at' => $cursor, 'stopped_at' => $stop]);
                $cursor = $stop->copy()->addMinutes(mt_rand(20, 90));
            }
        }
    }

    private function discuss(Task $task, TaskAssignment $assignment, User $user, User $reviewer, Carbon $begin, Carbon $end, array $notes, int $reworks): void
    {
        $say = fn (User $who, string $body, Carbon $at) => $task->comments()->create(['assignment_id' => $assignment->id, 'user_id' => $who->id, 'body' => $body])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        $say($user, 'Взял(а) в работу, план: '.mb_strtolower($assignment->scope).'.', $begin->copy()->addMinutes(30));
        $span = max(1, $begin->diffInHours($end));
        foreach ($notes as $i => $note) {
            $at = $begin->copy()->addHours((int) ($span * ($i + 1) / (count($notes) + 1)));
            $say($reviewer, 'Возвращаю на доработку: '.$note, $at);
            $say($user, $i % 2 ? 'Понял, поправлю.' : 'Исправил(а), отправляю повторно.', $at->copy()->addHours(3));
        }
        if ($assignment->status === 'done') $say($reviewer, $reworks ? 'Теперь всё ок, принято.' : 'Отличная работа, принято с первого раза.', $end);
    }

    private function syncEpic(Task $epic): void
    {
        $states = $epic->children()->pluck('status');
        $status = $states->every(fn ($s) => $s === 'done') ? 'done' : ($states->contains(fn ($s) => $s !== 'todo') ? 'in_progress' : 'todo');
        $last = $epic->children()->max('completed_at');
        $epic->forceFill(['status' => $status, 'completed_at' => $status === 'done' ? $last : null])->save();
    }

    private function describe(string $title, ?string $scope): string
    {
        return "{$scope}.\n\nКритерии приёмки:\n— соответствует макету и требованиям эпика\n— покрыто тестами или проверено вручную\n— нет регрессий в соседних экранах";
    }
}
