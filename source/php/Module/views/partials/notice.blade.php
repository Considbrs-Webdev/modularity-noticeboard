@card([
    'link' => $notice['permalink'],
    'classList' => array_merge(
        ['c-card--noticeboard', 'noticeboard-notice'],
        !empty($isLast) ? ['is-last'] : ['u-margin__bottom--3']
    )
])
    <div class="c-card__header u-padding__bottom--0">
        @typography([
            'element' => 'h3',
            'variant' => $noticeTitleVariant,
            'classList' => ['noticeboard-notice-title']
        ])
            {{ $notice['title'] ?? '' }}
        @endtypography
    </div>
    <div class="c-card__body">
        @typography([
            'element' => 'span',
            'classList' => ['noticeboard-notice-content']
        ])
            {!! $notice['content'] !!}
        @endtypography
    </div>
    <div class="c-card__footer">
        @tags([
            'compress' => 4,
            'tags' => $notice['group'],
            'icon' => $groupIcon,
            'format' => false,
            'classList' => $buttons ? ['u-margin__top--2'] : []
        ])
        @endtags
    </div>
@endcard
