<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SuperadminAuditLog;
use App\Models\Tenant;
use App\Tenancy\TenantException;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Firmen verwalten (nur Betreiber). Firmen werden nie gelöscht, nur gesperrt: Die Daten gehören dem Kunden
 * und unterliegen Aufbewahrungspflichten. Das Datenbank-Passwort verlässt den Server nie.
 */
class TenantController extends Controller
{
    public function __construct(private TenantProvisioner $provisioner) {}

    public function index(): JsonResponse
    {
        return response()->json(Tenant::orderBy('name')->get()->map(fn (Tenant $t) => $this->data($t)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subdomain' => ['required', 'string', 'max:63'],
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_name' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $tenant = $this->provisioner->create(collect($data)->except(['admin_email', 'admin_name'])->all());
        } catch (TenantException $e) {
            return $this->refuse($e);
        }
        SuperadminAuditLog::record('tenant_created', null, $tenant->id, ['subdomain' => $tenant->subdomain, 'database' => $tenant->db_database]);

        $temporary = null;
        $adminError = null;
        try {
            $temporary = $this->provisioner->createAdmin($tenant, $data['admin_email'], $data['admin_name'] ?? null ?: 'Administrator');
            SuperadminAuditLog::record('tenant_admin_created', null, $tenant->id, ['email' => mb_strtolower($data['admin_email'])]);
        } catch (TenantException $e) {
            $adminError = $e->getMessage();
        }

        return response()->json($this->data($tenant) + ['temporary_password' => $temporary, 'admin_error' => $adminError], 201);
    }

    /** Name, Sperre und Zugangsdaten ändern. Unterdomäne und Datenbankname bleiben fest (dort liegen die Daten). */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_username' => ['required', 'string', 'max:64'],
            // Leer lassen = Passwort bleibt wie es ist.
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $before = ['name' => $tenant->name, 'is_active' => $tenant->is_active, 'db_host' => $tenant->db_host, 'db_port' => $tenant->db_port, 'db_username' => $tenant->db_username];
        $passwordChanged = filled($data['db_password'] ?? null);

        $tenant->fill([
            'name' => $data['name'], 'is_active' => $data['is_active'],
            'db_host' => $data['db_host'], 'db_port' => $data['db_port'], 'db_username' => $data['db_username'],
        ]);
        if ($passwordChanged) {
            $tenant->db_password = $data['db_password'];
        }

        // Geänderte Zugangsdaten vor dem Speichern prüfen: Falsche Angaben würden die Firma sofort lahmlegen.
        if ($tenant->isDirty(['db_host', 'db_port', 'db_username', 'db_password'])) {
            try {
                $this->provisioner->checkConnection($tenant);
            } catch (TenantException $e) {
                return $this->refuse($e);
            }
        }

        $tenant->save();

        $changes = collect($before)->filter(fn ($v, $k) => $v !== $tenant->{$k})->map(fn ($v, $k) => ['von' => $v, 'zu' => $tenant->{$k}])->all();
        if ($passwordChanged) {
            $changes['db_password'] = 'geändert';
        }
        $event = array_key_exists('is_active', $changes) ? ($tenant->is_active ? 'tenant_unlocked' : 'tenant_locked') : 'tenant_updated';
        SuperadminAuditLog::record($event, null, $tenant->id, $changes ?: null);

        return response()->json($this->data($tenant->fresh()));
    }

    public function migrate(Tenant $tenant): JsonResponse
    {
        try {
            $this->provisioner->migrate($tenant);
        } catch (TenantException $e) {
            return $this->refuse($e);
        }
        SuperadminAuditLog::record('tenant_migrated', null, $tenant->id);

        return response()->json($this->data($tenant->fresh()));
    }

    /** Neuen Administrator in der Firma anlegen, z. B. wenn der einzige Zugang verloren ist. */
    public function createAdmin(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $temporary = $this->provisioner->createAdmin($tenant, $data['email'], ($data['name'] ?? null) ?: 'Administrator');
        } catch (TenantException $e) {
            return $this->refuse($e);
        }
        SuperadminAuditLog::record('tenant_admin_created', null, $tenant->id, ['email' => mb_strtolower($data['email'])]);

        return response()->json(['email' => mb_strtolower($data['email']), 'temporary_password' => $temporary], 201);
    }

    private function refuse(TenantException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], 422);
    }

    private function data(Tenant $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'subdomain' => $t->subdomain,
            'host' => $t->subdomain.'.'.config('tenancy.base_domain'),
            'db_host' => $t->db_host,
            'db_port' => $t->db_port,
            'db_database' => $t->db_database,
            'db_username' => $t->db_username,
            'is_active' => $t->is_active,
            'migrated_at' => $t->migrated_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
