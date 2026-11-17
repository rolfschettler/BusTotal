<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Der eine Weg, einen Kunden mit Nummern anzulegen. Die Anwendung (CustomerController) und die
 * Demo-Daten benutzen ihn beide, damit Kunden- und Debitorennummern überall gleich vergeben werden.
 */
class CustomerService
{
    public function __construct(private readonly NumberRangeService $numbers) {}

    /** Legt den Kunden samt Nummern an. Wird er nicht gespeichert, verfallen auch die Nummern nicht. */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = new Customer($data);
            $customer->forceFill($this->nextNumbers());
            $customer->save();

            return $customer;
        });
    }

    /**
     * Die nächsten Nummern. Nur innerhalb einer Transaktion aufrufen, in der der Kunde auch gespeichert
     * wird (siehe NumberRangeService::next).
     *
     * @return array{customer_number: string, debitor_number: int}
     */
    public function nextNumbers(): array
    {
        return [
            'customer_number' => $this->numbers->next('kunde'),
            'debitor_number' => (int) $this->numbers->next('debitor'),
        ];
    }
}
