<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $request->validate(['role' => 'in:user,moderator,admin']);
            $query->role($request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->paginate(20));
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:user,moderator,admin',
        ]);

        // S2: Prevent self-demotion
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.'
            ], 403);
        }

        // S2: Prevent removing the last admin
        if ($user->hasRole('admin') && $validated['role'] !== 'admin') {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Impossible de rétrograder le dernier administrateur du système.'
                ], 403);
            }
        }

        $user->syncRoles([$validated['role']]);

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        // S2: Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        // S2: Prevent deleting the last admin
        if ($user->hasRole('admin')) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Impossible de supprimer le dernier administrateur du système.'
                ], 403);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé'], 200);
    }
}
