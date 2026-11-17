<?php

namespace App\Http\Requests;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Eingaberegeln für Kunden anlegen und ändern. */
class CustomerRequest extends FormRequest
{
    // Wer darf überhaupt? Das regelt schon die Route (role:...), hier immer ja.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = CustomerType::tryFrom((string) $this->input('type'));
        $isOrg = $type?->isOrganisation() ?? true;

        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            // Organisationen brauchen einen Firmennamen, Privatkunden einen Nachnamen.
            'company_name' => [$isOrg ? 'required' : 'nullable', 'string', 'max:255'],
            'salutation' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => [$isOrg ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{2}[A-Z0-9]{2,12}$/'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Bitte einen Firmennamen angeben.',
            'last_name.required' => 'Bitte einen Nachnamen angeben.',
        ];
    }
}
