<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\NumberRange;
use App\Models\TaxRate;
use App\Services\NumberRangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    // ---------- Firmendaten ----------

    public function company(): JsonResponse
    {
        return response()->json(CompanySetting::current());
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'vat_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{2}[A-Z0-9]{2,12}$/'],
            'commercial_register' => ['nullable', 'string', 'max:255'],
            'managing_director' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', fn ($attr, $value, $fail) => self::validIban($value) || $fail('Die IBAN ist ungültig.')],
            'bic' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'letterhead_note' => ['nullable', 'string', 'max:2000'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        if (! empty($data['iban'])) {
            $data['iban'] = strtoupper(str_replace(' ', '', $data['iban']));
        }

        $company = CompanySetting::current();
        $company->update($data);

        return response()->json($company->fresh());
    }

    // ---------- Steuersätze ----------

    public function taxRates(): JsonResponse
    {
        return response()->json(TaxRate::orderByDesc('rate')->get());
    }

    public function storeTaxRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:tax_rates,name'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'active' => ['boolean'],
        ]);

        return response()->json(TaxRate::create($data)->fresh(), 201);
    }

    public function updateTaxRate(Request $request, TaxRate $taxRate): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tax_rates', 'name')->ignore($taxRate->id)],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'active' => ['required', 'boolean'],
        ]);
        $taxRate->update($data);

        return response()->json($taxRate->fresh());
    }

    // ---------- Nummernkreise ----------

    public function numberRanges(NumberRangeService $numbers): JsonResponse
    {
        return response()->json(
            NumberRange::orderBy('id')->get()->map(fn (NumberRange $r) => $r->toArray() + [
                'preview' => $numbers->preview($r),
            ]),
        );
    }

    public function updateNumberRange(Request $request, NumberRange $numberRange, NumberRangeService $numbers): JsonResponse
    {
        $data = $request->validate([
            'prefix' => ['present', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]*$/'],
            'include_year' => ['required', 'boolean'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'reset_yearly' => ['required', 'boolean'],
            'next_number' => ['required', 'integer', 'min:1'],
        ]);
        $data['prefix'] ??= '';

        // GoBD: Die nächste Nummer darf nie sinken, sonst könnten Nummern doppelt vergeben werden.
        if ($data['next_number'] < $numberRange->next_number) {
            return response()->json([
                'message' => 'Die nächste Nummer darf nicht verkleinert werden.',
                'errors' => ['next_number' => ['Die nächste Nummer darf nicht kleiner sein als '.$numberRange->next_number.'.']],
            ], 422);
        }

        $numberRange->update($data);

        return response()->json($numberRange->fresh()->toArray() + ['preview' => $numbers->preview($numberRange->fresh())]);
    }

    /** IBAN-Prüfung nach ISO 13616 (Modulo 97). */
    private static function validIban(?string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', (string) $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rest = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $rest = (int) ($rest.$chunk) % 97;
        }

        return $rest === 1;
    }
}
