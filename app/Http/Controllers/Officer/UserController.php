<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('officer.users.index', ['users' => User::query()->orderBy('name')->get(), 'roles' => Roles::LABELS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(Roles::OFFICERS)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['name' => __('jina'), 'email' => __('barua pepe'), 'role' => __('cheo'), 'password' => __('nenosiri')]);

        User::query()->create($data + ['group_id' => $this->groupId()]);

        return back()->with('status', __('Mtumiaji :name ameongezwa.', ['name' => $data['name']]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(Roles::OFFICERS)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'system_admin') {
            return back()->with('error', __('Huwezi kujiondolea cheo cha msimamizi mwenyewe.'));
        }

        $user->update(array_filter($data));

        return back()->with('status', __('Taarifa za :name zimesasishwa.', ['name' => $user->name]));
    }
}
