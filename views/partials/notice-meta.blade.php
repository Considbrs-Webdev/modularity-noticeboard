{{--
    Notice single metadata: type badge, notice date, take-down date.
    Semantic dl for dates; row matches archive badge + date layout.
--}}
<div class="c-notice-single__meta u-margin__bottom--5">
    @if (!empty($noticeSingleTags))
        <div class="c-notice-single__row">
            @tags([
                'compress' => 4,
                'tags' => $noticeSingleTags,
                'format' => false,
                'classList' => ['c-notice-single__tags'],
            ])
            @endtags
        </div>
    @endif

    @if (!empty($noticePublishDateFormatted) || !empty($noticeTakeDownDateFormatted))
        <dl class="c-notice-single__dates u-margin__top--2 u-margin__bottom--0">
            @if (!empty($noticePublishDateFormatted))
                <div class="c-notice-single__date-row">
                    <dt class="c-notice-single__term">{{ __('Anslagsdatum', 'modularity-noticeboard') }}:</dt>
                    <dd class="c-notice-single__desc">
                        <time datetime="{{ $noticePublishDateIso ?? '' }}">{{ $noticePublishDateFormatted }}</time>
                    </dd>
                </div>
            @endif
            @if (!empty($noticeTakeDownDateFormatted))
                <div class="c-notice-single__date-row">
                    <dt class="c-notice-single__term">{{ __('Nedtagningsdatum', 'modularity-noticeboard') }}:</dt>
                    <dd class="c-notice-single__desc">
                        @if (!empty($noticeTakeDownDateIso))
                            <time datetime="{{ $noticeTakeDownDateIso }}">{{ $noticeTakeDownDateFormatted }}</time>
                        @else
                            {{ $noticeTakeDownDateFormatted }}
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    @endif
</div>
