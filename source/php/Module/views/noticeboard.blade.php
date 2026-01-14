@if (!$hideTitle)
    @typography([
        'element' => 'h4'
    ])
        {{ $postTitle }}
    @endtypography
@endif
