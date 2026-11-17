<?php

namespace App\Services;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\NumberRange;
use App\Support\DemoDataGenerator;
use App\Support\ImportId;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Legt Demo-Kunden an und entfernt sie wieder.
 *
 * Die Kunden bekommen ihre Nummern über denselben Weg wie in der Anwendung (CustomerService), also
 * lückenlos und fortlaufend. Jeder Demo-Datensatz trägt eine feste UUID (siehe ImportId) und die
 * Markierung "is_demo". Dadurch gilt:
 *  - Ein zweiter Lauf legt nichts doppelt an.
 *  - Entfernen trifft nur Demo-Kunden, nie echte.
 *
 * Es entsteht nur ein Eintrag im Änderungsprotokoll (nicht einer je Kunde), sonst würde ein Demo-Lauf
 * das Protokoll mit Hunderten Zeilen füllen.
 */
class DemoDataService
{
    public const SOURCE = 'demo';

    public function __construct(private readonly CustomerService $customers) {}

    /**
     * @param  callable(int $done, int $total): void|null  $progress
     * @return array{created: int, skipped: int, archived: int, contacts: int, addresses: int, first_number: ?string, last_number: ?string}
     */
    public function generate(int $count, ?callable $progress = null): array
    {
        $generator = new DemoDataGenerator(CarbonImmutable::now());

        // Der Reihe nach erzeugen (die Namen müssen eindeutig bleiben), danach nach Alter sortieren,
        // damit ältere Kunden die kleineren Nummern bekommen, wie in einem echten Bestand.
        $all = [];
        for ($i = 1; $i <= $count; $i++) {
            $all[$i] = $generator->make($i);
        }
        uasort($all, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        $existing = Customer::withTrashed()
            ->whereIn('id', array_map(fn ($i) => ImportId::for(self::SOURCE, 'kunde', $i), array_keys($all)))
            ->pluck('id')->flip();

        $stats = ['created' => 0, 'skipped' => 0, 'archived' => 0, 'contacts' => 0, 'addresses' => 0, 'first_number' => null, 'last_number' => null];
        $done = 0;

        foreach ($all as $index => $data) {
            $id = ImportId::for(self::SOURCE, 'kunde', $index);

            if ($existing->has($id)) {
                $stats['skipped']++;
            } else {
                $number = $this->store($id, $index, $data);
                $stats['created']++;
                $stats['archived'] += $data['archived'] ? 1 : 0;
                $stats['contacts'] += count($data['contacts']);
                $stats['addresses'] += count($data['addresses']);
                $stats['first_number'] ??= $number;
                $stats['last_number'] = $number;
            }

            if ($progress) {
                $progress(++$done, $count);
            }
        }

        if ($stats['created'] > 0) {
            AuditLog::record('demo_data_created', null, null, ['created' => $stats['created'], 'from' => $stats['first_number'], 'to' => $stats['last_number']]);
        }

        return $stats;
    }

    /**
     * Entfernt alle Demo-Kunden samt Ansprechpartnern und Adressen (auch archivierte).
     * Gibt es danach gar keine Kunden mehr, beginnen Kunden- und Debitorennummern wieder am Anfang:
     * Das hilft bei Schulungen, die jedes Mal bei K-00001 starten sollen. Mit echten Kunden im
     * Bestand bleiben die Zähler unangetastet.
     *
     * @return array{removed: int, counters_reset: bool}
     */
    public function remove(): array
    {
        $removed = Customer::withTrashed()->where('is_demo', true)->forceDelete();
        $reset = false;

        if ($removed > 0 && ! Customer::withTrashed()->exists()) {
            NumberRange::withoutEvents(function () {
                NumberRange::where('type', 'kunde')->update(['next_number' => 1, 'last_year' => null]);
                NumberRange::where('type', 'debitor')->update(['next_number' => 10000, 'last_year' => null]);
            });
            $reset = true;
        }

        if ($removed > 0) {
            AuditLog::record('demo_data_removed', null, null, ['removed' => $removed, 'counters_reset' => $reset]);
        }

        return ['removed' => $removed, 'counters_reset' => $reset];
    }

    /** Speichert einen Kunden mit allem Zubehör. Gibt seine Kundennummer zurück. */
    private function store(string $id, int $index, array $data): string
    {
        return DB::transaction(function () use ($id, $index, $data) {
            $stamps = ['created_at' => $data['created_at'], 'updated_at' => $data['updated_at']];

            // Die Nummernzähler ändern sich bei jedem Kunden; diese Technik-Einträge sollen nicht
            // einzeln ins Änderungsprotokoll (400 Zeilen für 200 Kunden).
            $numbers = NumberRange::withoutEvents(fn () => $this->customers->nextNumbers());

            $customer = new Customer;
            $customer->forceFill([
                'id' => $id, 'is_demo' => true, 'deleted_at' => $data['archived'] ? $data['updated_at'] : null,
            ] + $numbers + $data['customer'] + $stamps)->saveQuietly(); // saveQuietly: ohne Protokoll-Eintrag je Kunde

            foreach ($data['contacts'] as $j => $contact) {
                (new Contact)->forceFill(['id' => ImportId::for(self::SOURCE, 'kontakt', "$index-$j"), 'customer_id' => $id] + $contact + $stamps)->saveQuietly();
            }
            foreach ($data['addresses'] as $j => $address) {
                (new Address)->forceFill(['id' => ImportId::for(self::SOURCE, 'adresse', "$index-$j"), 'customer_id' => $id] + $address + $stamps)->saveQuietly();
            }

            return $numbers['customer_number'];
        });
    }
}
