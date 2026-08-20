<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $card['student']->full_name }} — ID Card</title>
    <style>
        @include('id-cards._card-styles')
        .id-card-page + .id-card-page { page-break-before: always; }
    </style>
</head>
<body>
    <div class="id-card-page">@include('id-cards._card-front', ['card' => $card])</div>
    <div class="id-card-page">@include('id-cards._card-back', ['card' => $card])</div>
</body>
</html>
