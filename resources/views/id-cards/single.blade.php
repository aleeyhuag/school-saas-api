<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $card['student']->full_name }} — ID Card</title>
    <style>
        @page { margin: 0; }
        @include('id-cards._card-styles')
    </style>
</head>
<body>
    @include('id-cards._card-front', ['card' => $card])
    @include('id-cards._card-back', ['card' => $card])
</body>
</html>
