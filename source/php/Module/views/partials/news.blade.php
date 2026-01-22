@newsItem([
    'heading' => $notice['title'],
    'link' => $notice['permalink'],
    'content' => $notice['content'],
    'date' => [
        'timestamp' => $notice['publishedTimestamp']
    ]
])
    @slot('headerLeftArea')
        @tags([
            'compress' => 4,
            'tags' => $notice['tags'],
            'format' => false,
            'classList' => $buttons ? ['u-margin__top--2'] : []
        ])
        @endtags
    @endslot
@endnewsItem
