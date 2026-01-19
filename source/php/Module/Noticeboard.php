<?php

namespace ModularityNoticeboard;

use ModularityNoticeboard\Data\Posttype;

class Noticeboard extends \Modularity\Module
{
    public $slug = 'noticeboard';
    public $icon = 'background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cGF0aCBkPSJNMTQuNTI5LDE3Ljk3YzAtMi41NzEtMS42NDctNC43NTctMy45NDEtNS41NjlWOC4xMTcgICAgYzAtMS4wODktMC44ODMtMS45NzEtMS45Ny0xLjk3MWMtMS4wODcsMC0xLjk3MSwwLjg4MS0xLjk3MSwxLjk3MXY0LjI4NGMtMi4yOTQsMC44MTItMy45NDEsMi45OTgtMy45NDEsNS41NjkgICAgYzAsMi41NzEsMS42NDcsNC43NTcsMy45NDEsNS41Njl2MjAuMzkyYy0yLjI5NCwwLjgxMi0zLjk0MSwyLjk5OC0zLjk0MSw1LjU2OXMxLjY0Nyw0Ljc1NywzLjk0MSw1LjU2OXYyMC4zOTIgICAgYy0yLjI5NCwwLjgxMi0zLjk0MSwyLjk5OC0zLjk0MSw1LjU2OXMxLjY0Nyw0Ljc1NywzLjk0MSw1LjU2OXY0LjI4M2MwLDEuMDksMC44ODMsMS45NzEsMS45NzEsMS45NzEgICAgYzEuMDg3LDAsMS45Ny0wLjg4MSwxLjk3LTEuOTcxVjg2LjZjMi4yOTQtMC44MTIsMy45NDEtMi45OTgsMy45NDEtNS41NjlzLTEuNjQ3LTQuNzU4LTMuOTQxLTUuNTY5VjU1LjA2OSAgICBjMi4yOTQtMC44MTIsMy45NDEtMi45OTgsMy45NDEtNS41NjlzLTEuNjQ3LTQuNzU3LTMuOTQxLTUuNTY5VjIzLjUzOUMxMi44ODEsMjIuNzI4LDE0LjUyOSwyMC41NDEsMTQuNTI5LDE3Ljk3eiBNOTMuMzU0LDEwLjA4NyAgICBIMzYuMjA2YzAsMC0yLjY0LTAuMjM1LTMuNDQ5LDAuNTdMMjQuOTQsMTYuNDhjLTAuODA4LDAuODA1LTAuODA4LDIuMTA5LDAsMi45MTRsNy44MTcsNS44MjMgICAgYzAuODA5LDAuODA1LDMuNDQ5LDAuNjM1LDMuNDQ5LDAuNjM1aDU3LjE0OGMyLjE3OSwwLDMuOTQxLTEuNzYzLDMuOTQxLTMuOTQxdi03Ljg4MkM5Ny4yOTUsMTEuODUxLDk1LjUzMiwxMC4wODcsOTMuMzU0LDEwLjA4N3ogICAgIE05My4zNTQsNDEuNjE4SDM2LjIwNmMwLDAtMi42NC0wLjIzNS0zLjQ0OSwwLjU2OWwtNy44MTcsNS44MjRjLTAuODA4LDAuODA0LTAuODA4LDIuMTA5LDAsMi45MTNsNy44MTcsNS44MjMgICAgYzAuODA5LDAuODA1LDMuNDQ5LDAuNjM2LDMuNDQ5LDAuNjM2aDU3LjE0OGMyLjE3OSwwLDMuOTQxLTEuNzYzLDMuOTQxLTMuOTQxdi03Ljg4M0M5Ny4yOTUsNDMuMzgsOTUuNTMyLDQxLjYxOCw5My4zNTQsNDEuNjE4eiAgICAgTTkzLjM1NCw3My4xNDdIMzYuMjA2YzAsMC0yLjY0LTAuMjM0LTMuNDQ5LDAuNTY5TDI0Ljk0LDc5LjU0Yy0wLjgwOCwwLjgwNS0wLjgwOCwyLjEwOSwwLDIuOTE0bDcuODE3LDUuODIzICAgIGMwLjgwOSwwLjgwNSwzLjQ0OSwwLjYzNSwzLjQ0OSwwLjYzNWg1Ny4xNDhjMi4xNzksMCwzLjk0MS0xLjc2MywzLjk0MS0zLjk0MXYtNy44ODJDOTcuMjk1LDc0LjkxLDk1LjUzMiw3My4xNDcsOTMuMzU0LDczLjE0N3oiLz48L3N2Zz4=);';
    public $supports = array();
    public $isBlockCompatible = true;

    private $wpService;

    public function init()
    {
        $this->nameSingular = __('Noticeboard', 'modularity-noticeboard');
        $this->namePlural = __('Noticeboards', 'modularity-noticeboard');
        $this->description = __('Display noticeboard messages', 'modularity-noticeboard');
    }

    public function data(): array
    {
        $this->wpService = \Modularity\Helper\WpService::get();
        $fields = $this->getFields();

        $noticesToShow = $data['noticesToShow'] ?? 5;

        $data = [
            'groupByNoticeType' => isset($fields['group_by_notice_type']) ? $fields['group_by_notice_type'] : true,
        ];

        if (isset($this->hideTitle) && $this->hideTitle !== false) {
            $data['titleVariant'] = $this->wpService->applyFilters('Modularity/Module/Noticeboard/TitleVariant', 'h2');
        }
        
        $data['noticeTitleVariant'] = $this->wpService->applyFilters('Modularity/Module/Noticeboard/NoticeTitleVariant', 'h4');
        $data['notices'] = $this->getNotices($data['groupByNoticeType'], $noticesToShow);

        $data['groupIcon'] = ['icon' => $this->wpService->applyFilters('Modularity/Module/Noticeboard/GroupIcon', 'account_balance')];

        return $data;
    }

    public function getNotices($groupByNoticeType = true, $noticesToShow = 5)
    {
        $postType = Posttype::NOTICE_POST_TYPE;
        $taxonomy = Posttype::NOTICE_TAXONOMY;

        $args = [
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $noticesToShow,
            'orderby' => 'post_date',
            'order' => 'DESC',
        ];

        $query = new \WP_Query($args);
        $posts = $query->posts ?: [];

        // Helper to map a WP_Post to an array used by the view
        $map_post = function($p) {
            return [
                'title' => get_the_title($p),
                'content' => $this->getContent($p),
                'permalink' => get_permalink($p),
                'group' => $this->getGroup($p),
            ];
        };

        if (!$groupByNoticeType) {
            $result = array_map($map_post, $posts);
            wp_reset_postdata();
            return $result;
        }

        // Group by taxonomy terms
        $groups = [];

        foreach ($posts as $p) {
            $terms = wp_get_post_terms($p->ID, $taxonomy);

            if (is_wp_error($terms) || empty($terms)) {
                $tid = 0;
                if (!isset($groups[$tid])) {
                    $groups[$tid] = [
                        'term' => [
                            'term_id' => 0,
                            'name' => '',
                            'description' => '',
                            'slug' => '',
                        ],
                        'notices' => [],
                    ];
                }
                $groups[$tid]['notices'][] = $map_post($p);
                continue;
            }

            foreach ($terms as $t) {
                $tid = intval($t->term_id);
                if (!isset($groups[$tid])) {
                    $groups[$tid] = [
                        'term' => [
                            'term_id' => $t->term_id,
                            'name' => $t->name,
                            'description' => $t->description,
                            'slug' => $t->slug,
                        ],
                        'notices' => [],
                    ];
                }
                $groups[$tid]['notices'][] = $map_post($p);
            }
        }

        wp_reset_postdata();

        // Convert associative groups to indexed array and return
        return array_values($groups);
    }

    private function getContent($post)
    {
        $dateFormat = get_option('date_format');
        $content = '<span class="label">%s:</span> ' . get_the_date('', $post);

        $archiveDate = get_field('archive_date', $post->ID);
        if ($archiveDate) {
            $content .= '<br><span class="label">%s:</span> ' . date_i18n($dateFormat, strtotime($archiveDate));
        }

        $content = sprintf(
            $content,
            $this->wpService->applyFilters('Modularity/Module/Noticeboard/PublishedLabel', __('Published', 'modularity-noticeboard')),
            $this->wpService->applyFilters('Modularity/Module/Noticeboard/ArchiveDateLabel', __('Archive date', 'modularity-noticeboard'))
        );

        return $this->wpService->applyFilters('Modularity/Module/Noticeboard/NoticeContent', $content, $post);
    }

    private function getGroup($post)
    {
        $groupTaxonomy = Posttype::NOTICE_GROUP_TAXONOMY;
        $terms = wp_get_post_terms($post->ID, $groupTaxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $term = $terms[0];

        return [
            [
                'label' => $term->name
            ]
        ];
    }

    /**
     * Blade Template
     * @return string
     */
    public function template(): string
    {
        return 'noticeboard.blade.php';
    }

    /**
     * Available "magic" methods for modules:
     * init()            What to do on initialization (if you must, use __construct with care, this will probably break stuff!!)
     * data()            Use to send data to view (return array)
     * style()           Enqueue style only when module is used on page
     * script            Enqueue script only when module is used on page
     * adminEnqueue()    Enqueue scripts for the module edit/add page in admin
     * template()        Return the view template (blade) the module should use when displayed
     */
}
