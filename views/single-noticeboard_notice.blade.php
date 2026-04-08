@extends('templates.single')

@section('loop')
    @if (!empty($post))
        {!! $hook->innerLoopStart !!}
        @element([
            'componentElement' => 'article',
            'id' => 'article',
            'classList' => ['c-article', 'c-article--readable-width', 's-article', 'u-clearfix']
        ])
            @section('article.title.before')@show
            @section('article.title')
                @if (method_exists($post, 'getTitle') ? $post->getTitle() : $post->post_title)
                    @typography([
                        'element' => 'h1',
                        'variant' => 'h1',
                        'id' => 'page-title',
                        'classList' => ['u-margin__bottom--4']
                    ])
                        {!! method_exists($post, 'getTitle') ? $post->getTitle() : $post->post_title !!}
                    @endtypography
                @endif
            @show
            @section('article.title.after')@show

            @includeWhen(
                !empty($noticePublishDateFormatted ?? null) || !empty($noticeTakeDownDateFormatted ?? null) || !empty($noticeSingleTags ?? null),
                'partials.notice-meta'
            )

            @section('article.content.before')@show
            {!! $hook->articleContentBefore ?? '' !!}
            @section('article.content')
                {!! is_object($post) && method_exists($post, 'getContent') ? $post->getContent() : $post->post_content !!}
            @show
            @section('article.content.after')@show

            {!! $hook->articleContentAfter ?? '' !!}

            @section('content.below')
                @includeWhen(
                    !empty($signature),
                    'partials.signature',
                    array_merge((array) ($signature ?? []), ['classList' => []]))
            @endsection
        @endelement
        {!! $hook->innerLoopEnd !!}
    @endif
@stop
