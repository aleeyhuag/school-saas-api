<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ID Card Print Sheet</title>
    <style>
        @include('id-cards._card-styles')

        @page { margin: 24pt 20pt; }
        .sheet-page { page-break-after: always; }
        .sheet-page:last-child { page-break-after: auto; }
        .sheet-grid { width: 100%; border-collapse: separate; border-spacing: 10pt; }
        .sheet-grid td { padding: 0; }
        .cut-hint { font-size: 6pt; color: #999; text-align: center; padding-top: 20pt; }
    </style>
</head>
<body>
    @foreach($cardChunks as $chunk)
        <div class="sheet-page">
            <table class="sheet-grid">
                {{-- 2 cards per row, matching IdCardPdfService::CARDS_PER_SHEET's 2x4 layout --}}
                @foreach($chunk->chunk(2) as $row)
                    <tr>
                        @foreach($row as $card)
                            <td>@include('id-cards._card', ['card' => $card])</td>
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
