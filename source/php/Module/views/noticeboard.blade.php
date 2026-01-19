@if (!$hideTitle)
    @typography([
        'element' => 'h2',
        'variant' => $titleVariant ?? 'h2',
        'classList' => ['noticeboard-title', 'u-margin__bottom--2']
    ])
        {{ $postTitle }}
    @endtypography
@endif

@if (!empty($groupByNoticeType))
    @foreach ($notices as $group)
        @group()
            @include('partials.notice-term', ['term' => $group['term'] ?? [], 'isLast' => $loop->last])

            @foreach ($group['notices'] ?? [] as $notice)
                @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
            @endforeach
        @endgroup
    @endforeach
@else
    @foreach ($notices as $notice)
        @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
    @endforeach
@endif
