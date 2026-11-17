<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Gleiche Fehlermeldung für "unbekannt", "falsches Passwort" und "gesperrt",
        // damit niemand herausfinden kann, welche E-Mail-Adressen existieren.
        if (! $user || ! $user->active || ! Hash::check($data['password'], $user->password)) {
            AuditLog::record('login_failed', $user, null, ['email' => $data['email']], $user?->id);

            throw ValidationException::withMessages([
                'email' => ['Die Anmeldedaten sind ungültig.'],
            ]);
        }

        AuditLog::record('login', $user, userId: $user->id);

        return response()->json([
            'token' => $user->createToken('angular')->plainTextToken,
            'user' => $this->userData($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::record('logout', $request->user());
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Abgemeldet.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userData($request->user()));
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'must_change_password' => $user->must_change_password,
        ];
    }
}
