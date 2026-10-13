<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8|confirmed',
            'department' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // M7: wrap user creation + role assignment in a transaction
            $result = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name'       => $request->name,
                    'email'      => $request->email,
                    'password'   => Hash::make($request->password),
                    'department' => $request->department,
                ]);
                $user->assignRole('user');
                $token = $user->createToken('auth-token')->plainTextToken;
                return compact('user', 'token');
            });

            return response()->json([
                'user'  => [
                    'id'         => $result['user']->id,
                    'name'       => $result['user']->name,
                    'email'      => $result['user']->email,
                    'department' => $result['user']->department,
                    'roles'      => $result['user']->getRoleNames(),
                ],
                'token' => $result['token'],
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Le service est temporairement indisponible (erreur base de données).'], 503);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Une erreur inattendue est survenue lors de l\'inscription.'], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'errors' => [
                        'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.']
                    ]
                ], 422);
            }

            $user->loadCount(['resources', 'favorites', 'reviews']);
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user'  => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'department' => $user->department,
                    'roles'      => $user->getRoleNames(),
                    'resources_count' => $user->resources_count,
                    'favorites_count' => $user->favorites_count,
                    'reviews_count'   => $user->reviews_count,
                ],
                'token' => $token,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Le service est temporairement indisponible (erreur base de données).'], 503);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Une erreur inattendue est survenue lors de la connexion.'], 500);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->loadCount(['resources', 'favorites', 'reviews']);
        return new UserResource($user);
    }
}
