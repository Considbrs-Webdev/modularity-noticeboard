@if (!$hideTitle)
    @typography([
        'element' => 'h2',
        'variant' => $titleVariant ?? 'h2',
        'classList' => ['noticeboard-title', 'u-margin__top--0', 'u-margin__bottom--2']
    ])
        {{ $postTitle }}
    @endtypography
@endif

<div class="noticeboard noticeboard--display-{{ $displayAs }}">
    @if (!empty($groupByNoticeType))
        @foreach ($notices as $group)
            @group([
                'direction' => 'vertical',
                'classList' => ['noticeboard-group', $loop->last ? 'u-margin__bottom--0' : 'u-margin__bottom--4']
            ])
                @include('partials.notice-term', ['term' => $group['term'] ?? [], 'isLast' => $loop->last])

                <div class="noticeboard__notices">
                    @foreach ($group['notices'] as $notice)
                        @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
                    @endforeach
                </div>
            @endgroup
        @endforeach
    @else
        <div class="noticeboard__notices">
            @foreach ($notices as $notice)
                @include('partials.notice', ['notice' => $notice, 'isLast' => $loop->last])
            @endforeach
        </div>
    @endif
</div>

@if (!$archiveMode && $archiveLink !== false)
    @button([
        'text' => $archiveLabel,
        'href' => $archiveLink,
        'color' => $archiveButtonStyle['color'],
        'style' => $archiveButtonStyle['style'],
        'icon' => $archiveIcon,
        'classList' => ['noticeboard-archive-button', 'u-margin__top--4'],
    ])
    @endbutton
@endif
