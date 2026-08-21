<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ID Card Print Sheet</title>
    <style>
        @page { margin: 24pt 20pt; }
        @include('id-cards._card-styles')
        .sheet-page { page-break-after: always; }
        .sheet-page:last-child { page-break-after: auto; }
        {{--
            table-layout: fixed + an explicit width (242.65pt per
            column, exactly matching the card's own width, rather than
            width: 100%) is what makes column sizing deterministic.
            Without it, the table stretches to fill the full A4
            content width and lets the browser/dompdf's auto-layout
            algorithm decide how to distribute the extra space --
            which is the most likely explanation for cards rendering
            smaller than intended relative to the page.
        --}}
        .sheet-grid { width: 505.3pt; table-layout: fixed; border-collapse: separate; border-spacing: 10pt; }
        .sheet-grid td { width: 242.65pt; padding: 0; vertical-align: top; }
        .sheet-page-label { font-size: 7pt; color: #7d8883; margin-bottom: 4pt; }
    </style>
</head>
<body>
    @foreach($cardChunks as $chunk)
        <div class="sheet-page">
            <div class="sheet-page-label">Fronts — sheet {{ $loop->iteration }}</div>
            <table class="sheet-grid">
                @foreach($chunk->chunk(2) as $row)
                    <tr>
                        @foreach($row as $card)
                            <td>@include('id-cards._card-front', ['card' => $card])</td>
                        @endforeach
                        @if($row->count() < 2)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="sheet-page">
            <div class="sheet-page-label">Backs — sheet {{ $loop->iteration }}</div>
            <table class="sheet-grid">
                @foreach($chunk->chunk(2) as $row)
                    <tr>
                        @foreach($row as $card)
                            <td>@include('id-cards._card-back', ['card' => $card])</td>
                        @endforeach
                        @if($row->count() < 2)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
