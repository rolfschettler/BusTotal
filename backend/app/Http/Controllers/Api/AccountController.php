<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/** Das eigene Konto: Passwort ändern. */
class AccountController extends Controller
{
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ], [
            'current_password.required' => 'Bitte das aktuelle Passwort eingeben.',
            'password.required' => 'Bitte ein neues Passwort eingeben.',
            'password.confirmed' => 'Die beiden neuen Passwörter stimmen nicht überein.',
            'password.different' => 'Das neue Passwort muss sich vom bisherigen unterscheiden.',
            'password.min' => 'Das Passwort muss mindestens 10 Zeichen lang sein.',
            'password.letters' => 'Das Passwort muss mindestens einen Buchstaben enthalten.',
            'password.numbers' => 'Das Passwort muss mindestens eine Ziffer enthalten.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Das aktuelle Passwort ist nicht korrekt.']]);
        }

        // Mit dem eigenen Passwort verschwindet das aufbewahrte vorläufige Passwort.
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false, 'temporary_password' => null])->save();

        // Alle anderen Anmeldungen beenden (andere Geräte, gestohlene Token). Die aktuelle bleibt.
        $current = $user->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? $current->id : null;
        $user->tokens()->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();

        // Das Passwort selbst steht nie im Protokoll, nur dass es geändert wurde.
        AuditLog::record('password_changed', $user);

        return response()->json(['message' => 'Passwort geändert.']);
    }
}
