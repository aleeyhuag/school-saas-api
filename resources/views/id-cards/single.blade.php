<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $card['student']->full_name }} — ID Card</title>
    <style>
        @include('id-cards._card-styles')
    </style>
</head>
<body>
    @include('id-cards._card', ['card' => $card])
</body>
</html>
