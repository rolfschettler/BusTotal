<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Superadmin;
use App\Models\SuperadminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/** Anmeldung der Betreiber (zentrale Datenbank, nur an der Betreiber-Adresse erreichbar). */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Superadmin::where('email', mb_strtolower($data['email']))->first();

        // Gleiche Meldung für "unbekannt", "falsches Passwort" und "gesperrt".
        if (! $admin || ! $admin->active || ! Hash::check($data['password'], $admin->password)) {
            SuperadminAuditLog::record('login_failed', $admin?->id, null, ['email' => $data['email']]);

            throw ValidationException::withMessages(['email' => ['Die Anmeldedaten sind ungültig.']]);
        }

        $admin->forceFill(['last_login_at' => now()])->save();
        SuperadminAuditLog::record('login', $admin->id);

        return response()->json([
            'token' => $admin->createToken('superadmin')->plainTextToken,
            'user' => $this->data($admin),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        SuperadminAuditLog::record('logout');
        $request->user('superadmin')->currentAccessToken()->delete();

        return response()->json(['message' => 'Abgemeldet.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->data($request->user('superadmin')));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(12)->letters()->numbers()],
        ], [
            'current_password.required' => 'Bitte das aktuelle Passwort eingeben.',
            'password.required' => 'Bitte ein neues Passwort eingeben.',
            'password.confirmed' => 'Die beiden neuen Passwörter stimmen nicht überein.',
            'password.different' => 'Das neue Passwort muss sich vom bisherigen unterscheiden.',
            'password.min' => 'Das Passwort muss mindestens 12 Zeichen lang sein.',
            'password.letters' => 'Das Passwort muss mindestens einen Buchstaben enthalten.',
            'password.numbers' => 'Das Passwort muss mindestens eine Ziffer enthalten.',
        ]);

        $admin = $request->user('superadmin');
        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => ['Das aktuelle Passwort ist nicht korrekt.']]);
        }

        $admin->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        $current = $admin->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? $current->id : null;
        $admin->tokens()->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();

        SuperadminAuditLog::record('password_changed');

        return response()->json(['message' => 'Passwort geändert.']);
    }

    private function data(Superadmin $a): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'must_change_password' => $a->must_change_password];
    }
}
