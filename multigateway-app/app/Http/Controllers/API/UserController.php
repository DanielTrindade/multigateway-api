<?php

namespace App\Http\Controllers\API;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('manage-users');

        $users = User::with('roles')->get();
        return UserResource::collection($users);
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

        return new UserResource($user->load('roles'));
    }

    public function show(User $user)
    {
        $this->authorize('manage-users');

        return new UserResource($user->load('roles'));
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

        return new UserResource($user->load('roles'));
    }


    public function destroy(User $user)
    {

        $this->authorize('manage-users');

        if ($user->id === auth()->id()) {
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

        // Encontrar a role correspondente
        $role = Role::where('name', $validatedData['role'])->first();

        if (!$role) {
            return response()->json([
                'message' => 'Role não encontrada'
            ], 422);
        }

        // Substituir todas as roles atuais pela nova role
        $user->roles()->sync([$role->id]);

        return new UserResource($user->load('roles'));
    }
}
