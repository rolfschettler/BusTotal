<?php

namespace App\Console\Commands;

use App\Models\Superadmin;
use App\Support\TemporaryPassword;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Legt ein Betreiber-Konto an (zentrale Datenbank). Das erste muss man so anlegen, danach geht es
 * auch in der Betreiber-Oberfläche nicht: weitere Konten bewusst nur auf der Kommandozeile.
 */
class SuperadminCreate extends Command
{
    protected $signature = 'superadmin:anlegen {email : E-Mail-Adresse} {name=Betreiber : Anzeigename}';

    protected $description = 'Legt ein Betreiber-Konto mit vorläufigem Passwort an (zentrale Datenbank)';

    public function handle(TenantManager $manager): int
    {
        $manager->useCentral();
        $email = mb_strtolower((string) $this->argument('email'));

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:superadmins,email']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $temporary = TemporaryPassword::generate(14);
        Superadmin::create([
            'name' => (string) $this->argument('name'), 'email' => $email, 'password' => $temporary,
            'active' => true, 'must_change_password' => true,
        ]);

        $this->info("Betreiber-Konto {$email} angelegt.");
        $this->line("Vorläufiges Passwort (wird nur jetzt angezeigt): {$temporary}");
        $this->line('Anmelden unter http://'.config('tenancy.superadmin_subdomain').'.'.config('tenancy.base_domain').' – das Passwort muss beim ersten Anmelden geändert werden.');

        return self::SUCCESS;
    }
}
