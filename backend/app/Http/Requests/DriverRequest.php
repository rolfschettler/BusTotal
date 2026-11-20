<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\LicenseClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Eingaberegeln für Fahrer anlegen und ändern. */
class DriverRequest extends FormRequest
{
    // Wer darf überhaupt? Das regelt schon die Route (role:...).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignore = $this->route('driver')?->id;

        return [
            'personnel_number' => ['required', 'string', 'max:30', Rule::unique('drivers', 'personnel_number')->ignore($ignore)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hired_on' => ['nullable', 'date_format:Y-m-d'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'license_classes' => ['nullable', 'array'],
            'license_classes.*' => ['string', 'distinct', Rule::enum(LicenseClass::class)],
            'license_number' => ['nullable', 'string', 'max:30'],
            'driver_card_number' => ['nullable', 'string', 'max:30'],
            'license_due_date' => ['nullable', 'date_format:Y-m-d'],
            'pbs_due_date' => ['nullable', 'date_format:Y-m-d'],
            'driver_card_due_date' => ['nullable', 'date_format:Y-m-d'],
            'driver_card_read_due_date' => ['nullable', 'date_format:Y-m-d'],
            'code95_due_date' => ['nullable', 'date_format:Y-m-d'],
            'medical_due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'personnel_number.unique' => 'Diese Personalnummer gibt es schon.',
            'first_name.required' => 'Bitte einen Vornamen angeben.',
            'last_name.required' => 'Bitte einen Nachnamen angeben.',
            'birth_date.before' => 'Das Geburtsdatum muss in der Vergangenheit liegen.',
        ];
    }
}
