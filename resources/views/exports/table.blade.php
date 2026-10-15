@php
    $cols      = $d['columns'];
    $widthSum  = max(1, array_sum(array_map(fn ($c) => $c['width'] ?? 15, $cols)));
    $pct       = fn ($c) => round((($c['width'] ?? 15) / $widthSum) * 100, 2);
    $keys      = array_column($cols, 'key');

    // The "Total" label spans every column in front of the first total value.
    $span = 1;
    if (! empty($d['totals'])) {
        foreach ($keys as $i => $k) {
            if (isset($d['totals']['values'][$k])) { $span = max(1, $i); break; }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $d['title'] }}</title>
    <style>
        @page { margin: 14mm 10mm 18mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #1f2933; }

        .brand   { font-size: 8pt; font-weight: bold; color: #1e5b31; letter-spacing: .5px; text-transform: uppercase; }
        h1       { font-size: 15pt; margin: 2px 0 4px 0; color: #111; }
        .sub     { font-size: 7.5pt; color: #6b7a72; margin: 0 0 2px 0; }

        table.summary { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin: 8px -4px 10px -4px; }
        table.summary td { border: 1px solid #d3dbd6; background: #f7faf8; padding: 5px 8px; width: 25%; }
        .s-label { font-size: 6.5pt; color: #6b7a72; }
        .s-value { font-size: 10pt; font-weight: bold; color: #111; }

        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        table.data th { background: #1e5b31; color: #fff; font-weight: bold; padding: 5px 5px; border: 1px solid #1e5b31; font-size: 7pt; }
        table.data td { padding: 4px 5px; border: 1px solid #d3dbd6; vertical-align: middle; word-wrap: break-word; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data tbody tr.alt td { background: #f7faf8; }
        table.data tr.muted td { color: #8a948e; text-decoration: line-through; }
        table.data tr.total td { background: #e6f4ea; font-weight: bold; border-top: 2px solid #1e5b31; }

        .l { text-align: left; } .r { text-align: right; } .c { text-align: center; }
        .empty { text-align: center; color: #6b7a72; padding: 14px 0; }
        .note { margin-top: 8px; font-size: 7pt; color: #6b7a72; font-style: italic; }

        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 6.5pt; color: #6b7a72; border-top: 1px solid #d3dbd6; padding-top: 3px; }
    </style>
</head>
<body>
    <div class="footer">
        Treklite Outdoor &middot; {{ $d['title'] }} &middot; Generated {{ now()->format('M d, Y h:i A') }}
    </div>

    <div class="brand">Treklite Outdoor</div>
    <h1>{{ $d['title'] }}</h1>
    @foreach ($d['subtitle'] as $line)
        <div class="sub">{{ $line }}</div>
    @endforeach

    @if (! empty($d['summary']))
        <table class="summary">
            @foreach (array_chunk($d['summary'], 4) as $chunk)
                <tr>
                    @foreach ($chunk as [$label, $value, $type])
                        <td>
                            <div class="s-label">{{ $label }}</div>
                            <div class="s-value">{{ \App\Support\TableExport::text(['type' => $type], $value) }}</div>
                        </td>
                    @endforeach
                    @for ($pad = count($chunk); $pad < 4; $pad++)
                        <td style="border-color:#fff; background:#fff;">&nbsp;</td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach ($cols as $c)
                    <th class="{{ ['left' => 'l', 'right' => 'r', 'center' => 'c'][$c['align'] ?? 'left'] }}" style="width: {{ $pct($c) }}%">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($d['rows'] as $n => $row)
                <tr class="{{ in_array($n, $d['muted'], true) ? 'muted' : ($n % 2 ? 'alt' : '') }}">
                    @foreach ($cols as $c)
                        <td class="{{ ['left' => 'l', 'right' => 'r', 'center' => 'c'][$c['align'] ?? 'left'] }}">{{ \App\Support\TableExport::text($c, $row[$c['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($cols) }}" class="empty">Nothing to show.</td></tr>
            @endforelse

            @if (! empty($d['totals']) && count($d['rows']) > 0)
                <tr class="total">
                    <td colspan="{{ $span }}" class="r">{{ $d['totals']['label'] }}</td>
                    @foreach ($cols as $i => $c)
                        @continue($i < $span)
                        <td class="{{ ['left' => 'l', 'right' => 'r', 'center' => 'c'][$c['align'] ?? 'left'] }}">{{ \App\Support\TableExport::text($c, $d['totals']['values'][$c['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if ($d['footnote'])
        <div class="note">{{ $d['footnote'] }}</div>
    @endif
</body>
</html>
