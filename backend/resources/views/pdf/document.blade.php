@php
    use App\Enums\DocumentType;

    $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : '';
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

    $isInvoice = $type->isInvoice();
    $title = $type->label();
    $addressLines = array_filter([
        $recipient['name'] ?? '',
        trim(($recipient['street'] ?? '')),
        trim(($recipient['postal_code'] ?? '').' '.($recipient['city'] ?? '')),
        (($recipient['country'] ?? 'DE') !== 'DE' && ! empty($recipient['country'])) ? $recipient['country'] : '',
    ], fn ($l) => $l !== '');
    $senderLine = collect([
        $seller['name'] ?? '',
        $seller['street'] ?? '',
        trim(($seller['postal_code'] ?? '').' '.($seller['city'] ?? '')),
    ])->filter()->implode(' · ');
    $notes = $lines->filter(fn ($l) => filled($l->tax_note))->values();
    $deductions = $doc->deductions ?? [];
    $tourSections = $doc->tour_snapshot['sections'] ?? [];
    // Was nach den Summen kommt, bleibt mit ihnen zusammen und steht nie allein auf einer Seite (siehe Stilblatt)
    $hasTour = $tourSections !== [];
    $hasNotes = $notes->isNotEmpty();
    $hasPay = $type === DocumentType::Gutschrift || ($isInvoice && $doc->due_date);
    $hasClosing = filled($doc->footer_text);
    $hasClosingParts = $hasNotes || $hasPay || $hasClosing;
    $km = fn ($v) => number_format((float) $v, 1, ',', '.').' km';
    $dur = function ($s) { $m = (int) round($s / 60); return intdiv($m, 60) > 0 ? intdiv($m, 60).' Std.'.($m % 60 ? ' '.($m % 60).' Min.' : '') : $m.' Min.'; };
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $doc->number }}</title>
<style>
    @page { margin: 18mm 18mm 28mm 25mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111; line-height: 1.35; }
    h1 { font-size: 14pt; margin: 0 0 2mm 0; color: {{ $brand }}; }
    table { border-collapse: collapse; }
    .muted { color: #555; }
    .right { text-align: right; }
    .small { font-size: 7pt; }
    .draft { background: #fff3cd; border: 1px solid #d39e00; color: #664d03; padding: 2mm 3mm; margin-bottom: 4mm; font-weight: bold; text-align: center; }

    .head { width: 100%; margin-bottom: 8mm; }
    .head td { vertical-align: top; }
    .logo { max-height: 18mm; max-width: 60mm; }
    .company-name { font-size: 12pt; font-weight: bold; color: {{ $brand }}; }

    .address-row { width: 100%; margin-bottom: 8mm; }
    .address-row td { vertical-align: top; }
    .sender-line { font-size: 6.5pt; color: #555; border-bottom: 0.3pt solid #999; padding-bottom: 0.5mm; margin-bottom: 1.5mm; }
    .meta td { padding: 0.4mm 0; }
    .meta td:first-child { color: #555; padding-right: 4mm; }

    .items { width: 100%; margin-top: 4mm; }
    .items th { background: {{ $brand }}; color: {{ $brandOn }}; text-align: left; padding: 1.6mm 1.5mm; font-size: 7.5pt; }
    .items td { padding: 1.6mm 1.5mm; border-bottom: 0.3pt solid #bbb; vertical-align: top; }
    .items tr { page-break-inside: avoid; }
    .items tr.keep-next { page-break-after: avoid; }
    .items tr.sum td { padding: 1mm 1.5mm; border-bottom: none; }
    .items tr.sum.first td { padding-top: 3mm; }
    .items tr.sum.total td { border-top: 0.6pt solid #111; font-weight: bold; font-size: 10pt; }
    .items tr.tail td { border-bottom: none; padding: 5mm 1.5mm 0 1.5mm; }
    .items tr.tail.tour td { padding-top: 2.5mm; }
    .items tr.tail.tour:first-of-type td { padding-top: 5mm; }

    footer { position: fixed; bottom: -20mm; left: 0; right: 0; font-size: 6.5pt; color: #555; border-top: 0.3pt solid #999; padding-top: 1.5mm; }
    footer table { width: 100%; }
    footer td { vertical-align: top; padding-right: 3mm; }
</style>
</head>
<body>

<footer>
    <table>
        <tr>
            <td>
                {{ $seller['name'] ?? '' }}@if (filled($seller['legal_form'] ?? null)) {{ $seller['legal_form'] }}@endif<br>
                {{ $seller['street'] ?? '' }}<br>
                {{ trim(($seller['postal_code'] ?? '').' '.($seller['city'] ?? '')) }}
            </td>
            <td>
                @if (filled($seller['phone'] ?? null)) Tel. {{ $seller['phone'] }}<br>@endif
                @if (filled($seller['email'] ?? null)) {{ $seller['email'] }}<br>@endif
                @if (filled($seller['website'] ?? null)) {{ $seller['website'] }}@endif
            </td>
            <td>
                @if (filled($seller['tax_number'] ?? null)) Steuernr. {{ $seller['tax_number'] }}<br>@endif
                @if (filled($seller['vat_id'] ?? null)) USt-IdNr. {{ $seller['vat_id'] }}<br>@endif
                @if (filled($seller['commercial_register'] ?? null)) {{ $seller['commercial_register'] }}<br>@endif
                @if (filled($seller['managing_director'] ?? null)) Geschäftsführung: {{ $seller['managing_director'] }}@endif
            </td>
            <td>
                @if (filled($seller['bank_name'] ?? null)) {{ $seller['bank_name'] }}<br>@endif
                @if (filled($seller['iban'] ?? null)) IBAN {{ $seller['iban'] }}<br>@endif
                @if (filled($seller['bic'] ?? null)) BIC {{ $seller['bic'] }}@endif
            </td>
        </tr>
    </table>
    @if (filled($seller['footer_text'] ?? null))
        <div style="margin-top: 1mm">{!! nl2br(e($seller['footer_text'])) !!}</div>
    @endif
    <div class="right">&nbsp;</div>{{-- Platz für „Seite x von y“ (wird nach dem Rendern eingesetzt, DocumentPdf::addPageNumbers) --}}
</footer>

@if ($isDraft)
    <div class="draft">ENTWURF – noch nicht festgeschrieben, ohne Belegnummer, keine gültige {{ $isInvoice ? 'Rechnung' : 'Unterlage' }}</div>
@endif

<table class="head">
    <tr>
        <td>
            @if (filled($seller['letterhead_note'] ?? null))
                <div class="small muted">{!! nl2br(e($seller['letterhead_note'])) !!}</div>
            @endif
        </td>
        <td class="right">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @else
                <span class="company-name">{{ $seller['name'] ?? '' }}</span>
            @endif
        </td>
    </tr>
</table>

<table class="address-row">
    <tr>
        <td style="width: 95mm">
            <div class="sender-line">{{ $senderLine }}</div>
            @foreach ($addressLines as $line)
                {{ $line }}<br>
            @endforeach
        </td>
        <td style="padding-left: 10mm">
            <table class="meta">
                @if ($doc->number)<tr><td>{{ $isInvoice ? 'Rechnungsnr.' : 'Belegnr.' }}</td><td><b>{{ $doc->number }}</b></td></tr>@endif
                <tr><td>Datum</td><td>{{ $date($doc->document_date) ?: ($isDraft ? 'wird beim Festschreiben gesetzt' : '') }}</td></tr>
                @if (filled($recipient['customer_number'] ?? null))<tr><td>Kundennr.</td><td>{{ $recipient['customer_number'] }}</td></tr>@endif
                @if ($doc->service_date_from)
                    <tr><td>Leistung</td><td>{{ $date($doc->service_date_from) }}@if ($doc->service_date_to && $doc->service_date_to != $doc->service_date_from) – {{ $date($doc->service_date_to) }}@endif</td></tr>
                @endif
                @if ($isInvoice && $doc->due_date && $type !== DocumentType::Gutschrift)<tr><td>Fällig am</td><td>{{ $date($doc->due_date) }}</td></tr>@endif
                @if ($doc->source && $type === DocumentType::Gutschrift)<tr><td>Storno zu</td><td>{{ $doc->source->number }}</td></tr>
                @elseif ($doc->source)<tr><td>Bezug</td><td>{{ $doc->source->type->label() }} {{ $doc->source->number }}</td></tr>@endif
            </table>
        </td>
    </tr>
</table>

<h1>{{ $title }}@if ($doc->number) {{ $doc->number }}@endif</h1>
@if (filled($doc->subject))
    <div style="font-weight: bold; margin-bottom: 3mm">{{ $doc->subject }}</div>
@endif
@if (filled($doc->intro_text))
    <div>{!! nl2br(e($doc->intro_text)) !!}</div>
@endif

<table class="items">
    <thead>
        <tr>
            <th style="width: 7mm">Pos.</th>
            <th>Bezeichnung</th>
            <th class="right" style="width: 20mm">Menge</th>
            <th class="right" style="width: 23mm">Einzelpreis</th>
            <th class="right" style="width: 12mm">MwSt.</th>
            <th class="right" style="width: 25mm">Netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr @class(['keep-next' => $loop->last])>
                <td>{{ $line->position }}</td>
                <td>
                    <b>{{ $line->title }}</b>
                    @if (filled($line->description))<br><span class="muted">{!! nl2br(e($line->description)) !!}</span>@endif
                </td>
                <td class="right">{{ $qty($line->quantity) }} {{ $line->unit }}</td>
                <td class="right">{{ $money($line->unit_price) }}</td>
                <td class="right">{{ $pct($line->tax_rate) }} %@if (filled($line->tax_note)) <sup>{{ $notes->search(fn ($n) => $n->id === $line->id) + 1 }}</sup>@endif</td>
                <td class="right">{{ $money($line->net_amount) }}</td>
            </tr>
        @endforeach
        {{-- Summen: Zeilen derselben Tabelle, damit sie mit der letzten Position zusammenbleiben --}}
        <tr class="sum keep-next first"><td colspan="2"></td><td colspan="3" class="right">Nettobetrag</td><td class="right">{{ $money($doc->net_total) }}</td></tr>
        @foreach (($doc->tax_summary ?? []) as $row)
            <tr class="sum keep-next"><td colspan="2"></td><td colspan="3" class="right muted">zzgl. {{ $pct($row['rate']) }} % MwSt. auf {{ $money($row['net']) }}</td><td class="right">{{ $money($row['tax']) }}</td></tr>
        @endforeach
        <tr @class(['sum', 'total' => empty($deductions), 'keep-next' => ! empty($deductions) || (! $hasTour && $hasClosingParts)])>
            <td colspan="2"></td>
            <td colspan="3" class="right">{{ empty($deductions) ? 'Gesamtbetrag' : 'Gesamtbetrag brutto' }}</td>
            <td class="right">{{ $money($doc->gross_total) }}</td>
        </tr>
        @if (! empty($deductions))
            @foreach ($deductions as $d)
                <tr class="sum keep-next">
                    <td colspan="2"></td>
                    <td colspan="3" class="right muted">abzüglich Anzahlung {{ $d['number'] }}@if (! empty($d['document_date'])) vom {{ $date($d['document_date']) }}@endif
                        (netto {{ $money($d['net']) }}, MwSt. {{ $money($d['tax']) }})</td>
                    <td class="right">{{ $money(-1 * (float) $d['gross']) }}</td>
                </tr>
            @endforeach
            <tr @class(['sum', 'total', 'keep-next' => ! $hasTour && $hasClosingParts])><td colspan="2"></td><td colspan="3" class="right">Zu zahlen</td><td class="right">{{ $money($doc->payable_total) }}</td></tr>
        @endif

        {{-- Alles nach den Summen ebenfalls als Zeilen der Tabelle: Seitenumbruch-Regeln siehe Stilblatt (keep-next, nie geteilt) --}}
        @foreach ($tourSections as $sec)
            @php
                $t = $sec['trip'] ?? null;
                $own = $sec['own'] ?? null;
                $ownText = $own ? $km($own['loaded_km']).' · Fahrzeit '.$dur($own['drive_s']).' · '.$own['days'].($own['days'] == 1 ? ' Tag' : ' Tage') : '';
                $buses = collect($sec['vehicles'] ?? [])->map(fn ($v) => (($v['count'] ?? 1) > 1 ? $v['count'].' × ' : '').($v['label'] ?? 'Fahrzeug'))->implode(', ');
                if ($t) {
                    $stopText = collect($t['stops'])->map(fn ($st) => $st['label'].(($st['dwell_minutes'] ?? 0) > 0 ? ' ('.$st['dwell_minutes'].' Min.)' : ''))->implode(' → ').(! empty($t['round_trip']) ? ' → zurück zum Start' : '');
                    $emptyText = $t['empty_km'] > 0 ? ', '.$km($t['empty_km']).' Leerfahrt' : '';
                    $figures = $km($t['loaded_km']).' mit Fahrgästen'.$emptyText.' · Lenkzeit '.$dur($t['drive_s']).' · '.$t['days'].($t['days'] == 1 ? ' Tag' : ' Tage');
                }
            @endphp
            {{-- Fahrtbeschreibung: Stand beim Festschreiben, aus dem Beleg, nie aus dem Tour-Archiv. Die Überschrift gehört zum ersten Abschnitt. --}}
            <tr @class(['tail', 'tour', 'keep-next' => $loop->last && $hasClosingParts])>
                <td colspan="6">
                    @if ($loop->first)<div style="margin-bottom: 1.5mm"><b>Fahrtbeschreibung</b></div>@endif
                    <b>{{ $sec['kind_label'] }}: {{ $sec['title'] }}</b>
                    <span class="muted">{{ $buses !== '' ? ' · '.$buses : '' }}</span>
                    @if ($t)
                        <div class="small">Halte: {{ $stopText }}</div>
                        <div class="small muted">{{ $figures }}</div>
                        @foreach ($t['legs'] ?? [] as $leg)
                            <div class="small muted">{{ $leg['from'] }} → {{ $leg['to'] }}: {{ $km($leg['distance_m'] / 1000) }}, {{ $dur($leg['duration_s']) }}{{ $leg['empty'] ? ' (Leerfahrt)' : '' }}</div>
                        @endforeach
                    @elseif ($own)
                        <div class="small muted">{{ $ownText }}</div>
                    @endif
                </td>
            </tr>
        @endforeach

        @if ($hasNotes)
            <tr @class(['tail', 'keep-next' => $hasPay || $hasClosing])>
                <td colspan="6" class="small muted">
                    @foreach ($notes as $i => $n)
                        <sup>{{ $i + 1 }}</sup> Pos. {{ $n->position }}: {{ $n->tax_note }}<br>
                    @endforeach
                </td>
            </tr>
        @endif

        @if ($hasPay)
            <tr @class(['tail', 'keep-next' => $hasClosing])>
                <td colspan="6">
                    @if ($type === DocumentType::Gutschrift)
                        Dieser Beleg storniert {{ $doc->source ? $doc->source->type->label().' '.$doc->source->number : 'den Ursprungsbeleg' }} und gleicht ihn aus.
                    @else
                        Bitte überweisen Sie den Betrag {{ empty($deductions) ? '' : 'von '.$money($doc->payable_total).' ' }}bis zum <b>{{ $date($doc->due_date) }}</b>
                        @if (filled($seller['iban'] ?? null)) auf das Konto IBAN {{ $seller['iban'] }}@if (filled($seller['bic'] ?? null)), BIC {{ $seller['bic'] }}@endif @endif
                        unter Angabe der Rechnungsnummer {{ $doc->number ?? '' }}.
                    @endif
                </td>
            </tr>
        @endif

        @if ($hasClosing)
            <tr class="tail"><td colspan="6">{!! nl2br(e($doc->footer_text)) !!}</td></tr>
        @endif
    </tbody>
</table>

</body>
</html>
