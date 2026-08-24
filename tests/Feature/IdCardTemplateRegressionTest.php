<?php

it('keeps the single ID-card template as two direct CR80 page cards', function () {
    $single = file_get_contents(base_path('resources/views/id-cards/single.blade.php'));
    $front = file_get_contents(base_path('resources/views/id-cards/_card-front.blade.php'));
    $back = file_get_contents(base_path('resources/views/id-cards/_card-back.blade.php'));
    $styles = file_get_contents(base_path('resources/views/id-cards/_card-styles.blade.php'));

    expect($single)
        ->not->toContain('@page { margin: 0; size:')
        ->toContain("@include('id-cards._card-front'")
        ->toContain("@include('id-cards._card-back'");

    expect($front)->toContain('<div class="id-card-page">')->not->toContain('id-card-inner');
    expect($back)->toContain('<div class="id-card-page">')->not->toContain('id-card-inner');

    expect($styles)
        ->toContain('height: 152.55pt')
        ->toContain('page-break-after: always')
        ->toContain('page-break-after: auto')
        ->toContain('display: table-cell')
        ->toContain('vertical-align: middle');
});
