<?php

namespace ModularityNoticeboard\Helper;

use WPService\WpService;

class NoticeHelper
{
    /**
     * Get the publish date for a notice post
     *
     * @param \WP_Post $post
     * @return string
     */
    public static function getPublishDate($post): string
    {
        return get_the_date('', $post);
    }

    /**
     * Get the archive date for a notice post
     *
     * @param \WP_Post $post
     * @return string|null
     */
    public static function getArchiveDate($post): ?string
    {
        $archiveDate = get_field('archive_date', $post->ID);
        if ($archiveDate) {
            $dateFormat = get_option('date_format');
            return date_i18n($dateFormat, strtotime($archiveDate));
        }
        return null;
    }

    /**
     * Get the content for a notice post, including published and archive dates
     *
     * @param \WP_Post $post
     * @param WpService $wpService
     * @return string
     */
    public static function getContent($post): string
    {
        $wpService = \Modularity\Helper\WpService::get();
        $content = '<span class="label">%s:</span> ' . self::getPublishDate($post);

        $archiveDate = self::getArchiveDate($post);
        if ($archiveDate) {
            $content .= '<br><span class="label">%s:</span> ' . $archiveDate;
        }

        $content = sprintf(
            $content,
            $wpService->applyFilters('Modularity/Module/Noticeboard/PublishedLabel', __('Published', 'modularity-noticeboard')),
            $wpService->applyFilters('Modularity/Module/Noticeboard/ArchiveDateLabel', __('Archive date', 'modularity-noticeboard'))
        );

        $content = '<span class="noticeboard__meta">' . $content . '</span>';

        return $wpService->applyFilters('Modularity/Module/Noticeboard/NoticeContent', $content, $post);
    }
}
