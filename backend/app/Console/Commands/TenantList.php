<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantList extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'Zeigt alle Firmen (ohne Passwörter)';

    public function handle(): int
    {
        $rows = Tenant::orderBy('subdomain')->get()->map(fn (Tenant $t) => [
            $t->subdomain, $t->name, $t->db_database, $t->is_active ? 'aktiv' : 'gesperrt',
            $t->migrated_at?->format('d.m.Y H:i') ?? '–',
        ]);

        $this->table(['Unterdomäne', 'Name', 'Datenbank', 'Status', 'Zuletzt migriert'], $rows);

        return self::SUCCESS;
    }
}
