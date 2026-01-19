@if (!empty($term['name'] ?? ''))
    @typography([
        'element' => 'h3',
        'classList' => array_merge(['mb-2 mt-4'], !empty($isLast) ? ['is-last-group'] : [])
    ])
        {{ $term['name'] ?? '' }}
    @endtypography
@endif

@if (!empty($term['description'] ?? ''))
    @typography([
        'element' => 'div',
        'classList' => ['mb-3 noticeboard-term-description']
    ])
        {!! wpautop($term['description'] ?? '') !!}
    @endtypography
@endif
