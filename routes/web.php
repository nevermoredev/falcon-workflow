<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/assignments', [TaskController::class, 'addAssignment'])->name('tasks.assignments.store');
    Route::post('/tasks/{task}/assignments/{assignment}/reassign', [TaskController::class, 'reassign'])->name('tasks.assignments.reassign');
    Route::delete('/tasks/{task}/assignments/{assignment}', [TaskController::class, 'removeAssignment'])->name('tasks.assignments.destroy');
    Route::post('/tasks/{task}/claim', [TaskController::class, 'claim'])->name('tasks.claim');
    Route::post('/tasks/{task}/assignments/{assignment}/submit', [TaskController::class, 'submit'])->name('tasks.assignments.submit');
    Route::post('/tasks/{task}/assignments/{assignment}/review', [TaskController::class, 'review'])->name('tasks.assignments.review');
    Route::post('/tasks/{task}/checklist/{checklist}', [TaskController::class, 'toggleChecklist'])->name('tasks.checklist.toggle');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');

    Route::post('/tasks/{task}/assignments/{assignment}/start', [TimeEntryController::class, 'start'])->name('timer.start');
    Route::post('/timer/stop/{timeEntry?}', [TimeEntryController::class, 'stop'])->name('timer.stop');
    Route::get('/timer', [TimeEntryController::class, 'current'])->name('timer.current');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    Route::get('/reports/{tab?}', [ReportController::class, 'index'])->whereIn('tab', ['overview', 'people', 'time', 'deadlines', 'quality', 'tags'])->name('reports.index');
    Route::get('/reports/person/{user}', [ReportController::class, 'person'])->name('reports.person');
});
