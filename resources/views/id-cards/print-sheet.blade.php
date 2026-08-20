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
        .sheet-grid { width: 100%; border-collapse: separate; border-spacing: 10pt; }
        .sheet-grid td { padding: 0; vertical-align: top; }
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
