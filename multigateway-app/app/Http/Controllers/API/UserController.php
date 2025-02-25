<?php

namespace App\Http\Controllers\API;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\Request;
use App\Http\Controllers\Controller as Controller;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('manage-users');

        $users = User::all();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-users');

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
        ]);

        // Atribuir roles
        $roles = Role::whereIn('name', $validatedData['roles'])->get();
        $user->roles()->attach($roles);

        return response()->json($user->load('roles'), 201);
    }

    public function show(User $user)
    {
        $this->authorize('manage-users');

        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage-users');

        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $user->id,
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($request->has('password')) {
            $validatedData['password'] = Hash::make($request['password']);
        }

        $user->update($validatedData);

        // Atualizar roles se fornecidas
        if (isset($validatedData['roles'])) {
            $roles = Role::whereIn('name', $validatedData['roles'])->get();
            $user->roles()->sync($roles);
        }

        return response()->json($user->load('roles'));
    }


    public function destroy(User $user)
    {
        $this->authorize('manage-users');

        if ($user->id === User::id()) {
            return response()->json([
                'message' => 'Você não pode excluir seu próprio usuário'
            ], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    public function updateRole(Request $request, User $user)
    {
        $this->authorize('manage-users');

        $validatedData = $request->validate([
            'role' => 'required|in:ADMIN,MANAGER,FINANCE,USER',
        ]);

        $user->role = $validatedData['role'];
        $user->save();

        return response()->json($user);
    }
}
