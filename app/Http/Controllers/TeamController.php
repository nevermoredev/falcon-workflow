<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $people = User::withCount([
            'taskAssignments as active_count' => fn ($q) => $q->where('status', '!=', 'done'),
            'taskAssignments as done_count' => fn ($q) => $q->where('status', 'done'),
        ])->orderByRaw("role = 'manager' desc")->orderBy('name')->get();
        return view('team.index', compact('people'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'position' => ['nullable', 'string', 'max:120'], 'role' => ['required', 'in:employee,manager']]);
        User::create($data + ['password' => Hash::make('password')]);
        return back()->with('success', 'Сотрудник добавлен. Временный пароль: password');
    }
}
