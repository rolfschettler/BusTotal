<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\NumberRange;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/** Grundausstattung für jeden neuen Mandanten. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! CompanySetting::exists()) {
            CompanySetting::create(['name' => 'Neue Firma']);
        }

        foreach ([['Regelsteuersatz', 19], ['Ermäßigter Steuersatz', 7], ['Steuerfrei', 0]] as [$name, $rate]) {
            TaxRate::firstOrCreate(['name' => $name], ['rate' => $rate]);
        }

        $ranges = [
            ['kunde', 'Kundennummer', 'K', false, 5, false],
            ['debitor', 'Debitorennummer (DATEV)', '', false, 5, false],
            ['angebot', 'Angebot', 'AN', true, 5, true],
            ['auftrag', 'Auftrag', 'AU', true, 5, true],
            ['auftragsbestaetigung', 'Auftragsbestätigung', 'AB', true, 5, true],
            ['rechnung', 'Rechnung', 'RE', true, 5, true],
            ['gutschrift', 'Gutschrift / Storno', 'GS', true, 5, true],
            ['fahrtauftrag', 'Fahrtauftrag', 'FA', true, 5, true],
        ];
        foreach ($ranges as [$type, $label, $prefix, $year, $pad, $reset]) {
            NumberRange::firstOrCreate(['type' => $type], [
                'label' => $label, 'prefix' => $prefix, 'include_year' => $year,
                'padding' => $pad, 'reset_yearly' => $reset,
                // DATEV-Debitorenkonten beginnen üblicherweise bei 10000.
                'next_number' => $type === 'debitor' ? 10000 : 1,
            ]);
        }
    }
}
