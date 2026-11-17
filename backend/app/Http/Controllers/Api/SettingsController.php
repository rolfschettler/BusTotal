<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\NumberRange;
use App\Models\TaxRate;
use App\Services\NumberRangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            // Weißer Text auf der Markenfarbe muss lesbar bleiben (WCAG AA, Kontrast mindestens 4,5:1).
            'brand_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/', fn ($a, $v, $fail) => self::contrastWithWhite($v) >= 4.5 || $fail('Die Farbe ist zu hell: weiße Schrift darauf wäre schlecht lesbar.')],
        ]);

        if (isset($data['brand_color'])) {
            $data['brand_color'] = strtolower($data['brand_color']);
        }

        if (! empty($data['iban'])) {
            $data['iban'] = strtoupper(str_replace(' ', '', $data['iban']));
        }

        $company = CompanySetting::current();
        $company->update($data);

        return response()->json($company->fresh());
    }

    // ---------- Logo ----------

    public function uploadLogo(Request $request): JsonResponse
    {
        // Nur Pixelbilder, bewusst kein SVG (kann Skripte enthalten). Der Typ wird am Inhalt geprüft,
        // nicht am Dateinamen.
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], [
            'logo.mimes' => 'Erlaubt sind PNG, JPG und WebP.',
            'logo.max' => 'Das Logo darf höchstens 1 MB groß sein.',
        ]);

        $company = CompanySetting::current();
        $old = $company->logo_path;

        $file = $request->file('logo');
        $path = $file->storeAs('logos', bin2hex(random_bytes(8)).'.'.$file->extension(), 'local');

        $company->forceFill(['logo_path' => $path])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json($company->fresh());
    }

    public function deleteLogo(): JsonResponse
    {
        $company = CompanySetting::current();
        if ($company->logo_path) {
            Storage::disk('local')->delete($company->logo_path);
            $company->forceFill(['logo_path' => null])->save();
        }

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

    /** Kontrastverhältnis zwischen Weiß und der Farbe (WCAG 2.x). 21 = schwarz, 1 = weiß. */
    private static function contrastWithWhite(string $hex): float
    {
        $channel = function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $hex = ltrim($hex, '#');
        $l = 0.2126 * $channel(hexdec(substr($hex, 0, 2)))
           + 0.7152 * $channel(hexdec(substr($hex, 2, 2)))
           + 0.0722 * $channel(hexdec(substr($hex, 4, 2)));

        return 1.05 / ($l + 0.05);
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
