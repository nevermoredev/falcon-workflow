<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        return view('tags.index', ['tags' => Tag::withCount('tasks')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:40', 'unique:tags,name'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        Tag::create($data);
        return back()->with('success', 'Метка создана.');
    }

    public function destroy(Request $request, Tag $tag)
    {
        abort_unless($request->user()->isManager(), 403);
        $tag->delete();
        return back()->with('success', 'Метка удалена.');
    }
}
