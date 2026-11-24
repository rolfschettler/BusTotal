@php
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : '';
    $km = fn ($v) => number_format((float) $v, 1, ',', '.').' km';
    $dur = function ($s) { $m = (int) round($s / 60); return intdiv($m, 60) > 0 ? intdiv($m, 60).' Std.'.($m % 60 ? ' '.($m % 60).' Min.' : '') : $m.' Min.'; };

    $issued = $order->number !== null;
    $route = $order->route ?? [];
    $trip = $route['trip'] ?? null;
    $own = $route['own'] ?? null;
    $customer = $order->customer;
    $vehicle = $order->vehicle;
    $period = $date($order->date_from).($order->date_to && $order->date_to != $order->date_from ? ' – '.$date($order->date_to) : '');
    $busLabel = $order->vehicle_type?->label();
    $stops = $trip['stops'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Fahrtauftrag {{ $order->number }}</title>
<style>
    @page { margin: 15mm 15mm 22mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; line-height: 1.35; }
    h1 { font-size: 16pt; margin: 0 0 1mm 0; color: {{ $brand }}; }
    h2 { font-size: 10.5pt; margin: 6mm 0 2mm 0; color: {{ $brand }}; border-bottom: 0.6pt solid {{ $brand }}; padding-bottom: 0.8mm; page-break-after: avoid; }
    table { border-collapse: collapse; }
    .muted { color: #555; }
    .right { text-align: right; }
    .small { font-size: 7.5pt; }
    .draft { background: #fff3cd; border: 1px solid #d39e00; color: #664d03; padding: 2mm 3mm; margin-bottom: 4mm; font-weight: bold; text-align: center; }

    .head { width: 100%; margin-bottom: 4mm; }
    .head td { vertical-align: top; }
    .logo { max-height: 15mm; max-width: 55mm; }
    .company-name { font-size: 11pt; font-weight: bold; color: {{ $brand }}; }

    .facts { width: 100%; }
    .facts td { padding: 1.1mm 2mm 1.1mm 0; vertical-align: top; }
    .facts td.k { width: 32mm; color: #555; }
    .facts td.v { font-weight: bold; }

    .list { width: 100%; }
    .list th { background: {{ $brand }}; color: {{ $brandOn }}; text-align: left; padding: 1.4mm 1.5mm; font-size: 8pt; }
    .list td { padding: 1.4mm 1.5mm; border-bottom: 0.3pt solid #bbb; vertical-align: top; }
    .list tr { page-break-inside: avoid; }

    .fill { width: 100%; }
    .fill th { background: #eee; border: 0.4pt solid #666; text-align: left; padding: 1.2mm 1.5mm; font-size: 8pt; }
    .fill td { border: 0.4pt solid #666; height: 6.5mm; padding: 0 1.5mm; }
    .fill tr { page-break-inside: avoid; }
    .notes { border: 0.4pt solid #666; height: 16mm; }
    .sheet { page-break-before: always; }
    .sheet h2 { margin-top: 4mm; }

    footer { position: fixed; bottom: -15mm; left: 0; right: 0; font-size: 7pt; color: #555; border-top: 0.3pt solid #999; padding-top: 1.5mm; }
</style>
</head>
<body>

<footer>
    {{ $company->name }} · {{ $company->street }} · {{ trim($company->postal_code.' '.$company->city) }}@if (filled($company->phone)) · Tel. {{ $company->phone }}@endif
    <div class="right">&nbsp;</div>{{-- Platz für „Seite x von y“ (DocumentPdf::addPageNumbers) --}}
</footer>

@unless ($issued)
    <div class="draft">NOCH NICHT AUSGEGEBEN – ohne Fahrtauftragsnummer, nur zur Ansicht</div>
@endunless

<table class="head">
    <tr>
        <td>
            <h1>Fahrtauftrag @if ($issued){{ $order->number }}@endif</h1>
            <div class="muted">{{ $order->title }}@if ($busLabel) · {{ $busLabel }}@endif · Bus {{ $order->bus_no }}</div>
        </td>
        <td class="right">
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@else<span class="company-name">{{ $company->name }}</span>@endif
        </td>
    </tr>
</table>

<h2>Fahrt</h2>
<table class="facts">
    @if ($customer)<tr><td class="k">Auftraggeber</td><td class="v">{{ $customer->display_name }}</td></tr>@endif
    @if ($period !== '')<tr><td class="k">Termin</td><td class="v">{{ $period }}</td></tr>@endif
    @if (filled($order->staging_time))<tr><td class="k">Bereitstellung</td><td class="v">{{ $order->staging_time }} Uhr <span class="muted" style="font-weight: normal">(ab dann haben die Gäste Zugang zum Bus)</span></td></tr>@endif
    @if ($order->departure_at)<tr><td class="k">Abfahrt</td><td class="v">{{ $order->departure_at->copy()->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</td></tr>@endif
    @if (filled($order->passengers))<tr><td class="k">Personen</td><td class="v">{{ $order->passengers }}</td></tr>@endif
    @if ($order->section?->journey)<tr><td class="k">Reise</td><td>{{ $order->section->journey->title }}</td></tr>@endif
</table>

@if ($order->boardings->isNotEmpty())
    <h2>Zu- und Ausstiege</h2>
    <table class="list">
        <thead><tr><th style="width: 22mm">Uhrzeit</th><th style="width: 20mm">Art</th><th>Ort</th><th class="right" style="width: 40mm">Personen</th></tr></thead>
        <tbody>
            @foreach ($order->boardings as $b)
                <tr><td>{{ $b->time ? $b->time.' Uhr' : '' }}</td><td>{{ $b->kind === 'ausstieg' ? 'Ausstieg' : 'Zustieg' }}</td><td>{{ $b->place }}</td><td class="right">{{ $b->passengers }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Fahrzeug und Fahrer</h2>
<table class="facts">
    @if ($vehicle)
        <tr><td class="k">Fahrzeug</td><td class="v">{{ $vehicle->license_plate }} <span class="muted" style="font-weight: normal">({{ $vehicle->fleet_number }}, {{ $vehicle->type?->label() }}{{ $vehicle->seats ? ', '.$vehicle->seats.' Sitzplätze' : '' }})</span></td></tr>
    @elseif (filled($order->external_company))
        <tr><td class="k">Fremdunternehmer</td><td class="v">{{ $order->external_company }}@if (filled($order->external_plate)) · {{ $order->external_plate }}@endif</td></tr>
    @else
        <tr><td class="k">Fahrzeug</td><td class="muted">noch nicht zugewiesen ({{ $busLabel ?? 'Typ offen' }})</td></tr>
    @endif
    <tr><td class="k">Fahrer 1</td><td class="v">{{ $order->driver?->display_name ?? '—' }}</td></tr>
    @if ($order->driver2)<tr><td class="k">Fahrer 2</td><td class="v">{{ $order->driver2->display_name }}</td></tr>@endif
</table>

@if (filled($order->driver_note))
    <h2>Hinweise für den Fahrer</h2>
    <div>{!! nl2br(e($order->driver_note)) !!}</div>
@endif

<h2>Strecke</h2>
@if ($trip)
    <div class="small muted" style="margin-bottom: 1.5mm">
        {{ $km($trip['loaded_km']) }} mit Fahrgästen{{ ($trip['empty_km'] ?? 0) > 0 ? ', '.$km($trip['empty_km']).' Leerfahrt' : '' }}
        · Lenkzeit {{ $dur($trip['drive_s']) }} · {{ $trip['days'] }} {{ $trip['days'] == 1 ? 'Tag' : 'Tage' }}
    </div>
    <table class="list">
        <thead><tr><th style="width: 8mm">Nr.</th><th>Halt</th><th class="right" style="width: 28mm">Aufenthalt</th></tr></thead>
        <tbody>
            @foreach ($stops as $i => $stop)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $stop['label'] }}</td>
                    <td class="right">{{ ($stop['dwell_minutes'] ?? 0) > 0 ? $stop['dwell_minutes'].' Min.' : '' }}@if (! empty($stop['driver_free'])) <span class="small muted">(Fahrer frei)</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($trip['round_trip']))<div class="small muted" style="margin-top: 1mm">Danach zurück zum Start.</div>@endif
    @if (! empty($trip['legs']))
        <div style="margin-top: 3mm"><b>Teilstrecken</b></div>
        <table class="list">
            <tbody>
                @foreach ($trip['legs'] as $leg)
                    <tr>
                        <td>{{ $leg['from'] }} → {{ $leg['to'] }}@if ($leg['empty']) <span class="small muted">(Leerfahrt)</span>@endif</td>
                        <td class="right" style="width: 40mm">{{ $km($leg['distance_m'] / 1000) }} · {{ $dur($leg['duration_s']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@elseif ($own)
    <div>{{ $km($own['loaded_km']) }} · Fahrzeit {{ $dur($own['drive_s']) }} · {{ $own['days'] }} {{ $own['days'] == 1 ? 'Tag' : 'Tage' }}</div>
@else
    <div class="muted">Keine Streckenangaben im Auftrag.</div>
@endif

{{-- Erfassungsblatt: leere Felder zum Ausfüllen unterwegs, Rückerfassung im Büro (Etappe 3) --}}
<div class="sheet">
    <h1 style="font-size: 13pt">Erfassungsblatt {{ $order->number }}</h1>
    @if ($customer)<div style="margin-top: 1mm"><span class="muted">Auftraggeber:</span> <b>{{ $customer->display_name }}</b></div>@endif
    <div class="muted small">{{ collect([$order->title, $period, $vehicle?->license_plate ?? $order->external_plate])->filter()->implode(' · ') }}</div>

    <h2>Zeiten und Kilometerstände</h2>
    <table class="fill">
        <tr><th style="width: 46mm">Ereignis</th><th style="width: 26mm">Datum</th><th>Ort</th><th style="width: 22mm">Uhrzeit</th><th style="width: 28mm">km-Stand</th></tr>
        @foreach ($events as $e)
            <tr><td>{{ $e['event'] }}</td><td>{{ $e['date'] }}</td><td>{{ $e['place'] }}</td><td></td><td></td></tr>
        @endforeach
    </table>

    <h2>Grenzübertritte</h2>
    <table class="fill">
        <tr><th>Land</th><th style="width: 36mm">km-Stand Einfahrt</th><th style="width: 36mm">km-Stand Ausfahrt</th><th style="width: 36mm">km im Ausland</th></tr>
        @for ($i = 0; $i < 4; $i++)<tr><td></td><td></td><td></td><td></td></tr>@endfor
    </table>
    <div class="small muted" style="margin-top: 1mm">Je Land eine Zeile. „km im Ausland“ ist der Unterschied von Ausfahrt und Einfahrt.</div>

    <h2>Zusatzleistungen (Sonderfahrt, Wartezeit)</h2>
    <table class="fill">
        <tr><th>Leistung</th><th style="width: 24mm">Menge</th><th style="width: 32mm">bar bezahlt?</th></tr>
        @for ($i = 0; $i < 3; $i++)<tr><td></td><td></td><td></td></tr>@endfor
    </table>

    <h2>Unvorhergesehene Kosten (Maut, Parken, Fähre)</h2>
    <table class="fill">
        <tr><th>Art</th><th style="width: 32mm">Betrag</th><th style="width: 40mm">Beleg-Nr.</th></tr>
        @for ($i = 0; $i < 3; $i++)<tr><td></td><td></td><td></td></tr>@endfor
    </table>

    <h2>Notizen und Besonderheiten</h2>
    <div class="notes"></div>

    <table class="fill" style="margin-top: 5mm">
        <tr><td style="height: 12mm">Unterschrift Fahrer</td><td style="height: 12mm">Datum</td></tr>
    </table>
</div>

</body>
</html>
