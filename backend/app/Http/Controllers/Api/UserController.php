<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Benutzerverwaltung (nur Administrator).
 * Benutzer werden nie gelöscht, nur gesperrt: Das Änderungsprotokoll verweist auf sie (GoBD).
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        // Letzte erfolgreiche Anmeldung je Benutzer aus dem Protokoll.
        $lastLogins = AuditLog::where('event', 'login')
            ->selectRaw('user_id, MAX(created_at) as last_login')
            ->groupBy('user_id')
            ->pluck('last_login', 'user_id');

        $users = User::orderBy('name')->get()->map(fn (User $u) => $this->present($u) + [
            'last_login_at' => $lastLogins[$u->id] ?? null,
        ]);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $temporary = TemporaryPassword::generate();

        $user = new User([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $temporary,
            'role' => $data['role'],
            'active' => true,
            'must_change_password' => true,
        ]);
        $user->save();

        // Das vorläufige Passwort wird nur hier einmal herausgegeben und nirgends gespeichert.
        return response()->json($this->present($user) + ['temporary_password' => $temporary], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);
        $data['active'] = $request->validate(['active' => ['required', 'boolean']])['active'];

        if ($user->is($request->user()) && ($data['role'] !== $user->role->value || ! $data['active'])) {
            return $this->refuse('Sie können Ihre eigene Rolle und Ihren eigenen Status nicht ändern.');
        }

        $losesAdmin = $user->role === Role::Admin && $user->active
            && ($data['role'] !== Role::Admin->value || ! $data['active']);

        $refused = false;
        DB::transaction(function () use ($user, $data, $losesAdmin, &$refused) {
            // Es muss immer mindestens ein aktiver Administrator übrig bleiben. Die Zeilen werden
            // gesperrt, damit zwei Administratoren, die sich gleichzeitig gegenseitig sperren,
            // nicht beide durchkommen und niemanden übrig lassen.
            if ($losesAdmin) {
                $others = User::where('role', Role::Admin->value)->where('active', true)
                    ->where('id', '!=', $user->id)->lockForUpdate()->count();
                if ($others === 0) {
                    $refused = true;

                    return;
                }
            }

            $user->update([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'role' => $data['role'],
                'active' => $data['active'],
            ]);

            // Gesperrt heißt sofort abgemeldet, auf allen Geräten.
            if (! $data['active']) {
                $user->tokens()->delete();
            }
        });

        if ($refused) {
            return $this->refuse('Es muss mindestens ein aktiver Administrator vorhanden sein.');
        }

        return response()->json($this->present($user->fresh()));
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return $this->refuse('Ihr eigenes Passwort ändern Sie unter „Passwort ändern“ im Benutzermenü.');
        }

        $temporary = TemporaryPassword::generate();

        DB::transaction(function () use ($user, $temporary) {
            // temporary_password leeren: Ein früher aufbewahrtes Passwort wäre sonst veraltet.
            $user->forceFill(['password' => $temporary, 'must_change_password' => true, 'temporary_password' => null])->save();
            $user->tokens()->delete(); // altes Passwort und alte Anmeldungen sind sofort ungültig
            AuditLog::record('password_reset', $user);
        });

        return response()->json($this->present($user->fresh()) + ['temporary_password' => $temporary]);
    }

    // ---------- intern ----------

    private function validated(Request $request, ?User $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore?->id)],
            'role' => ['required', Rule::enum(Role::class)],
        ], [
            'name.required' => 'Bitte einen Namen angeben.',
            'email.required' => 'Bitte eine E-Mail-Adresse angeben.',
            'email.email' => 'Das ist keine gültige E-Mail-Adresse.',
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'role.required' => 'Bitte eine Rolle wählen.',
            'role.enum' => 'Diese Rolle gibt es nicht.',
        ]);
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['user' => [$message]]], 422);
    }

    private function present(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role->value,
            'active' => $u->active,
            'must_change_password' => $u->must_change_password,
            'created_at' => $u->created_at,
        ];
    }
}
