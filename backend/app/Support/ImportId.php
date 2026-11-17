<?php

namespace App\Support;

use Ramsey\Uuid\Uuid;

/**
 * Feste UUIDs für Datenübernahmen aus anderen Systemen.
 *
 * Aus Quellsystem, Art des Datensatzes und der dortigen Nummer wird immer dieselbe UUID abgeleitet
 * (UUID Version 5). Dadurch gilt:
 *  - Die ID eines Datensatzes ist bekannt, bevor er gespeichert ist. Man braucht keine Zuordnungstabelle
 *    "Altsystem-Nummer → neue ID" und muss nicht auf die ID des gespeicherten Kunden warten, um die
 *    Ansprechpartner vorzubereiten. (Beim Einfügen müssen die Kunden wegen der Fremdschlüssel trotzdem
 *    vor ihren Ansprechpartnern in der Datenbank stehen.)
 *  - Ein Import ist wiederholbar: Läuft er ein zweites Mal, trifft er dieselben Datensätze
 *    (updateOrCreate auf diese ID) und legt keine Dubletten an.
 *  - Mehrere Quellsysteme kollidieren nicht, weil das Quellsystem Teil der Ableitung ist.
 *
 * WICHTIG: "id" und die Nummern sind bei den Modellen gegen Zuweisung aus Formularen gesperrt. Ein Import
 * muss die Sperre bewusst aufheben, sonst wird die feste ID still verworfen und eine neue erzeugt:
 *
 *   $id = ImportId::for('altsystem-x', 'kunde', '4711');
 *   Customer::unguarded(fn () => Customer::updateOrCreate(['id' => $id], [
 *       'customer_number' => 'K-00042', 'type' => 'firma', 'company_name' => '…', 'country' => 'DE',
 *   ]));
 */
class ImportId
{
    /** Fester Namensraum von BusTotal. Darf sich NIE ändern, sonst entstehen bei erneutem Import neue IDs. */
    private const NAMESPACE_UUID = '6f1e3b5a-2c4d-5e7f-8a9b-0c1d2e3f4a5b';

    public static function for(string $sourceSystem, string $entity, string|int $sourceId): string
    {
        $name = mb_strtolower(trim($sourceSystem)).'|'.mb_strtolower(trim($entity)).'|'.trim((string) $sourceId);

        return Uuid::uuid5(self::NAMESPACE_UUID, $name)->toString();
    }
}
