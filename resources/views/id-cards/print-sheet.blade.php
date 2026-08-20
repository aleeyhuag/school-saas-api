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
        .sheet-page-label { font-size: 7pt; color: #999; margin-bottom: 4pt; }
    </style>
</head>
<body>
    {{--
        Each chunk of students produces TWO pages, back to back: every
        front, then every back, in the same grid position order. This
        keeps a printed sheet's fronts and backs matched up by
        POSITION on the page (top-left front pairs with top-left
        back, and so on) if you print the front page, then the back
        page, and line the two sheets up before cutting.

        This intentionally does NOT try to mirror left-right order for
        automatic double-sided (duplex) printing — whether that needs
        mirroring depends on your printer's flip-on-long-edge vs
        flip-on-short-edge setting, which isn't something to guess at.
        Printing front and back as two separate passes and aligning
        them by hand is the safer default here.
    --}}
    @foreach($cardChunks as $chunk)
        <div class="sheet-page">
            <div class="sheet-page-label">Fronts — sheet {{ $loop->iteration }}</div>
            <table class="sheet-grid">
                {{-- 2 cards per row, matching IdCardPdfService::CARDS_PER_SHEET's 2x4 layout --}}
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
