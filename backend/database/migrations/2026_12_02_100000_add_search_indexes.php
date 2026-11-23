<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Suche (App\Support\NameSearch) sucht am Anfang eines Feldes (`feld LIKE 'text%'`) und braucht dafür je Feld einen
     * Index, sonst liest die Datenbank bei jedem Tastendruck alle Zeilen. Fehlende Indizes werden hier ergänzt; schon
     * vorhandene (z. B. last_name, email, postal_code) bleiben unberührt.
     */
    private const INDEXES = [
        'customers' => ['first_name', 'city', ['last_name', 'first_name']],
        'drivers' => ['first_name', 'city', 'email', 'license_number', 'driver_card_number', ['last_name', 'first_name']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                $columns = (array) $columns;
                if (! $this->covered($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                $columns = (array) $columns;
                $name = $table.'_'.implode('_', $columns).'_index';
                if (collect(Schema::getIndexes($table))->contains('name', $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    /** Gibt es schon einen Index, der mit genau diesen Spalten beginnt? */
    private function covered(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($index) => array_slice($index['columns'], 0, count($columns)) === $columns);
    }
};
