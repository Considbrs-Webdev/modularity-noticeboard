@if (!$hideTitle)
    @typography([
        'element' => 'h2',
        'variant' => $titleVariant ?? 'h2',
        'classList' => ['noticeboard-title', 'u-margin__top--0', 'u-margin__bottom--2']
    ])
        {{ $postTitle }}
    @endtypography
@endif

@if (!empty($groupByNoticeType))
    @foreach ($notices as $group)
        @group([
            'direction' => 'vertical',
            'classList' => ['noticeboard-group', $loop->last ? 'u-margin__bottom--0' : 'u-margin__bottom--4']
        ])
            @include('partials.notice-term', ['term' => $group['term'] ?? [], 'isLast' => $loop->last])

            @foreach ($group['notices'] as $notice)
                @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
            @endforeach
        @endgroup
    @endforeach
@else
    @foreach ($notices as $notice)
        @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
    @endforeach
@endif
