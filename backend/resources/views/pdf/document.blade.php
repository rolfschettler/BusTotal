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

    .sums { width: 100%; margin-top: 3mm; page-break-inside: avoid; }
    .sums td { padding: 1mm 1.5mm; }
    .sums .total td { border-top: 0.6pt solid #111; font-weight: bold; font-size: 10pt; }

    .block { margin-top: 5mm; page-break-inside: avoid; }
    footer { position: fixed; bottom: -20mm; left: 0; right: 0; font-size: 6.5pt; color: #555; border-top: 0.3pt solid #999; padding-top: 1.5mm; }
    footer table { width: 100%; }
    footer td { vertical-align: top; padding-right: 3mm; }
    .pagenum:before { content: counter(page); }
    .pages:before { content: counter(pages); }
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
    <div class="right">Seite <span class="pagenum"></span> von <span class="pages"></span></div>
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
            <tr>
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
    </tbody>
</table>

<table class="sums">
    <tr><td></td><td class="right" style="width: 60mm">Nettobetrag</td><td class="right" style="width: 28mm">{{ $money($doc->net_total) }}</td></tr>
    @foreach (($doc->tax_summary ?? []) as $row)
        <tr><td></td><td class="right muted">zzgl. {{ $pct($row['rate']) }} % MwSt. auf {{ $money($row['net']) }}</td><td class="right">{{ $money($row['tax']) }}</td></tr>
    @endforeach
    <tr @class(['total' => empty($deductions)])>
        <td></td>
        <td class="right">{{ empty($deductions) ? 'Gesamtbetrag' : 'Gesamtbetrag brutto' }}</td>
        <td class="right">{{ $money($doc->gross_total) }}</td>
    </tr>
    @if (! empty($deductions))
        @foreach ($deductions as $d)
            <tr>
                <td></td>
                <td class="right muted">abzüglich Anzahlung {{ $d['number'] }}@if (! empty($d['document_date'])) vom {{ $date($d['document_date']) }}@endif
                    (netto {{ $money($d['net']) }}, MwSt. {{ $money($d['tax']) }})</td>
                <td class="right">{{ $money(-1 * (float) $d['gross']) }}</td>
            </tr>
        @endforeach
        <tr class="total"><td></td><td class="right">Zu zahlen</td><td class="right">{{ $money($doc->payable_total) }}</td></tr>
    @endif
</table>

@if ($notes->isNotEmpty())
    <div class="block small muted">
        @foreach ($notes as $i => $n)
            <sup>{{ $i + 1 }}</sup> Pos. {{ $n->position }}: {{ $n->tax_note }}<br>
        @endforeach
    </div>
@endif

@if ($isInvoice && $type !== DocumentType::Gutschrift)
    <div class="block">
        @if ($doc->due_date)
            Bitte überweisen Sie den Betrag {{ empty($deductions) ? '' : 'von '.$money($doc->payable_total).' ' }}bis zum <b>{{ $date($doc->due_date) }}</b>
            @if (filled($seller['iban'] ?? null)) auf das Konto IBAN {{ $seller['iban'] }}@if (filled($seller['bic'] ?? null)), BIC {{ $seller['bic'] }}@endif @endif
            unter Angabe der Rechnungsnummer {{ $doc->number ?? '' }}.
        @endif
    </div>
@elseif ($type === DocumentType::Gutschrift)
    <div class="block">Dieser Beleg storniert {{ $doc->source ? $doc->source->type->label().' '.$doc->source->number : 'den Ursprungsbeleg' }} und gleicht ihn aus.</div>
@endif

@if (filled($doc->footer_text))
    <div class="block">{!! nl2br(e($doc->footer_text)) !!}</div>
@endif

</body>
</html>
