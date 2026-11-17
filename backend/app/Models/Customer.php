<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Models\Concerns\Auditable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    // Nummern werden nie per Formular gesetzt, sondern vom Server vergeben. Ebenso die Demo-Markierung.
    protected $guarded = ['id', 'customer_number', 'debitor_number', 'is_demo'];

    // Dieselben Standardwerte wie in der Datenbank, damit auch ein frisch angelegtes Objekt sie kennt.
    protected $attributes = ['is_demo' => false];

    // Wird bei jeder JSON-Ausgabe mitgeschickt.
    protected $appends = ['display_name'];

    protected function casts(): array
    {
        return ['type' => CustomerType::class, 'is_demo' => 'boolean'];
    }

    /** Anzeigename: Firmenname oder "Vorname Nachname". */
    public function getDisplayNameAttribute(): string
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim($this->first_name.' '.$this->last_name) ?: $this->customer_number;
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderByDesc('is_primary')->orderBy('last_name');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderBy('id');
    }
}
