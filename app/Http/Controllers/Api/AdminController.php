<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->filled('role')) {
            $query->role($request->input('role')); // fourni par Spatie Permission
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

            return UserResource::collection($users);

    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:user,moderator,admin',
        ]);

        // Empêche un admin de se retirer lui-même son propre rôle admin par erreur
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.'
            ], 403);
        }

        $user->syncRoles([$validated['role']]);

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé'], 200);
    }
}
