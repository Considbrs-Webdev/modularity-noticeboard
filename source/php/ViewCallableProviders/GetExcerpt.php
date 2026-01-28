<?php
namespace ModularityNoticeboard\ViewCallableProviders;

use Municipio\PostsList\ViewCallableProviders\ViewCallableProviderInterface;
use Municipio\PostObject\PostObjectInterface;
use WpService\Contracts\WpTrimWords;

use ModularityNoticeboard\Helper\NoticeHelper;

/*
 * View utility to get excerpt with word count trimming
 */
class GetExcerpt implements ViewCallableProviderInterface
{
    public function __construct(private WpTrimWords $wpService) {}

    /**
     * Get the callable for the view utility
     *
     * @return callable
     */
    public function getCallable(): callable
    {
        return function(PostObjectInterface $post): string {
            $content = $post->getExcerpt();

            if (!empty($content)) {
                $content = $this->wpService->wpTrimWords(
                    $content,
                    20
                );
            }

            $content .= NoticeHelper::getContent(get_post($post->getId()));

            return $content;
        };
    }
}
