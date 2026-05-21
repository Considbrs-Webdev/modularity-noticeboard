<?php
namespace ModularityNoticeboard\Data;

use ModularityNoticeboard\Helper\NoticeHelper;
use ModularityNoticeboard\ViewCallableProviders\GetExcerpt;

/**
 * Class Template
 *
 * Adds view data for noticeboard templates.
 *
 * @package ModularityNoticeboard\Data
 */
class Template {
    public function __construct()
    {
        add_filter('Municipio/Template/'. Posttype::NOTICE_POST_TYPE .'/archive/viewData', function($data) {
            $wpService = \Modularity\Helper\WpService::get();

            $data['getExcerpt'] = (new GetExcerpt($wpService))->getCallable();
        
            return $data;
        }, 10, 1);

        add_filter(
            'Municipio/Template/' . Posttype::NOTICE_POST_TYPE . '/single/viewData',
            array($this, 'filterSingleViewData'),
            10,
            1
        );
    }

    /**
     * Add notice type, notice date, and take-down date for the single template.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterSingleViewData(array $data): array
    {
        $post = $data['post'] ?? null;
        if (!$post) {
            return $data;
        }

        $postId = 0;
        if (is_object($post) && method_exists($post, 'getId')) {
            $postId = (int) $post->getId();
        } elseif (is_object($post) && isset($post->id)) {
            $postId = (int) $post->id;
        }

        if ($postId < 1) {
            return $data;
        }

        $wpPost = get_post($postId);
        if (!$wpPost || $wpPost->post_type !== Posttype::NOTICE_POST_TYPE) {
            return $data;
        }

        $terms = get_the_terms($postId, Posttype::NOTICE_TAXONOMY);
        $typeName = '';
        $noticeTags = array();
        if (is_array($terms) && !empty($terms)) {
            $typeName = $terms[0]->name;
            foreach ($terms as $term) {
                $noticeTags[] = array(
                    'label' => $term->name,
                );
            }
        }

        $publishFormatted = NoticeHelper::getPublishDate($wpPost);
        $takeDownFormatted = NoticeHelper::getArchiveDate($wpPost);

        $publishIso = get_the_date('c', $postId);
        if (!is_string($publishIso)) {
            $publishIso = '';
        }

        $archiveRaw  = function_exists('get_field') ? get_field('archive_date', $postId) : null;
        $archiveTime = function_exists('get_field') ? get_field('archive_time', $postId) : null;
        $takeDownIso = '';
        if (is_string($archiveRaw) && $archiveRaw !== '') {
            if (is_string($archiveTime) && $archiveTime !== '') {
                $timezone    = wp_timezone();
                $dt          = new \DateTime($archiveRaw . ' ' . $archiveTime, $timezone);
                $takeDownIso = $dt->format('c');
            } else {
                $takeDownIso = $archiveRaw;
            }
        }

        $data['noticeSingleTags'] = $noticeTags;
        $data['noticeTypeName'] = $typeName;
        $data['noticePublishDateFormatted'] = $publishFormatted;
        $data['noticePublishDateIso'] = $publishIso;
        $data['noticeTakeDownDateFormatted'] = $takeDownFormatted;
        $data['noticeTakeDownDateIso'] = $takeDownIso;

        return $data;
    }
}