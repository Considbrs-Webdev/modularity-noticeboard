@group([
    'direction' => 'vertical',
    'classList' => ['noticeboard-term', 'u-margin__bottom--2']
])
    @if (!empty($term['name'] ?? ''))
        @typography([
            'element' => 'h3',
            'classList' => ['noticeboard-term-title', 'u-margin__bottom--1']
        ])
            {{ $term['name'] ?? '' }}
        @endtypography
    @endif

    @if (!empty($term['description'] ?? ''))
        @typography([
            'element' => 'p',
            'classList' => ['noticeboard-term-description']
        ])
            {{ $term['description'] }}
        @endtypography
    @endif
@endgroup
