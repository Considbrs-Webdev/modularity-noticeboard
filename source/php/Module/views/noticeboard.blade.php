@if (!$hideTitle)
    @typography([
        'element' => 'h4'
    ])
        {{ $postTitle }}
    @endtypography
@endif

@if (!empty($groupByNoticeType))
    @foreach ($notices as $group)
        @php
            $term = $group['term'] ?? [];
            $groupNotices = $group['notices'] ?? [];
        @endphp

        @if (!empty($term['name']))
            @typography([
                'element' => 'h5',
                'classList' => ['mb-2 mt-4']
            ])
                {{ $term['name'] }}
            @endtypography
        @endif

        @if (!empty($term['description']))
            @typography([
                'element' => 'div',
                'classList' => ['mb-3 noticeboard-term-description']
            ])
                {!! wpautop($term['description']) !!}
            @endtypography
        @endif

        @foreach ($groupNotices as $notice)
            <div class="mb-4">
                @typography([
                    'element' => 'h6',
                    'classList' => ['mb-1']
                ])
                    {{ $notice['title'] ?? '' }}
                @endtypography

                @typography([
                    'element' => 'div',
                    'classList' => ['noticeboard-notice-content']
                ])
                    {!! $notice['content'] ?? '' !!}
                @endtypography
            </div>
        @endforeach
    @endforeach
@else
    @foreach ($notices as $notice)
        <div class="mb-4">
            @typography([
                'element' => 'h6',
                'classList' => ['mb-1']
            ])
                {{ $notice['title'] ?? '' }}
            @endtypography

            @typography([
                'element' => 'div',
                'classList' => ['noticeboard-notice-content']
            ])
                {!! $notice['content'] ?? '' !!}
            @endtypography
        </div>
    @endforeach
@endif
