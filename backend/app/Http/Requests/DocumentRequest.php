<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Eingaberegeln für Belege (Entwurf anlegen und ändern). Strengere Prüfungen gibt es erst beim Festschreiben. */
class DocumentRequest extends FormRequest
{
    // Wer darf überhaupt? Das regelt schon die Route (role:...), hier immer ja.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Die Belegart steht beim Anlegen fest und kann später nicht geändert werden.
            'type' => [$this->isMethod('POST') ? 'required' : 'prohibited', Rule::enum(DocumentType::class)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'subject' => ['nullable', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string', 'max:10000'],
            'footer_text' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'document_date' => ['nullable', 'date'],
            'service_date_from' => ['nullable', 'date'],
            'service_date_to' => ['nullable', 'date'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'lines' => ['present', 'array', 'max:200'],
            'lines.*.title' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0', 'between:-9999999,9999999'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'numeric', 'between:-9999999,9999999'],
            'lines.*.tax_rate' => ['required', 'numeric', 'between:0,100'],
            'lines.*.tax_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.prohibited' => 'Die Belegart kann nachträglich nicht geändert werden.',
            'customer_id.exists' => 'Dieser Kunde existiert nicht oder ist archiviert.',
            'lines.*.title.required' => 'Bitte jede Position bezeichnen.',
            'lines.*.quantity.required' => 'Bitte eine Menge angeben.',
            'lines.*.quantity.not_in' => 'Die Menge darf nicht 0 sein.',
            'lines.*.unit_price.required' => 'Bitte einen Einzelpreis angeben.',
            'lines.*.tax_rate.required' => 'Bitte einen Steuersatz angeben.',
        ];
    }
}
