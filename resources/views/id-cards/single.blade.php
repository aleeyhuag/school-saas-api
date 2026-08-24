<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $card['student']->full_name }} — ID Card</title>
    <style>
        {{--
            Do NOT declare `size` here. IdCardPdfService::buildSingle()
            already calls setPaper([0, 0, CARD_WIDTH_PT, CARD_HEIGHT_PT])
            in PHP -- and dompdf's own Stylesheet.php unconditionally lets
            a CSS `@page { size: ... }` rule override whatever setPaper()
            set (confirmed directly in vendor/dompdf/dompdf/src/Css/
            Stylesheet.php: it reassigns paper_width/paper_height from
            $_page_styles['base']->size whenever that CSS rule is
            present). Having both here was silently discarding the
            PHP-side page size and is what caused the front/back card to
            render at the wrong dimensions -- the exact same category of
            "two competing size declarations" bug as the setPaper()
            array-plus-orientation issue documented in the ID card
            key-learnings, just via CSS this time instead of a PHP arg.
            setPaper() is now the single source of truth for the card's
            physical size; only margin belongs here.
        --}}
        @page { margin: 0; }
        @include('id-cards._card-styles')
    </style>
</head>
<body>
    <div class="id-card-page">@include('id-cards._card-front', ['card' => $card])</div>
    <div class="id-card-page">@include('id-cards._card-back', ['card' => $card])</div>
</body>
</html>
