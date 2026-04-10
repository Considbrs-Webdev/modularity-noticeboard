<?php

namespace ModularityNoticeboard\Frontend;

use ModularityNoticeboard\Admin\Settings;
use ModularityNoticeboard\Data\Posttype;

/**
 * Replaces Municipio's native CPT archive breadcrumb ("Notiser", etc.) with the
 * configured context page's ancestor trail + title. The href is the post type
 * archive URL on singles and on the CPT archive; on the context page view it uses
 * the page permalink so the trail matches the real URL.
 */
class Breadcrumbs
{
    private const CONTEXT_SINGLE = 'single';

    private const CONTEXT_ARCHIVE = 'archive';

    private const CONTEXT_LANDING_PAGE = 'landing_page';

    public function __construct()
    {
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'replaceArchiveBreadcrumbWithPagePath'], 99, 3);
    }

    /**
     * @param array<string, mixed>|null $items
     * @param mixed $queriedObject Unused; required for filter arity (Municipio passes queried object).
     * @param mixed $context Unused; Municipio passes 'municipio' or Modularity passes 'modularity'.
     * @return array<string, mixed>|null
     */
    public function replaceArchiveBreadcrumbWithPagePath($items, $queriedObject = null, $context = null): array|null
    {
        if (!is_array($items)) {
            return $items;
        }

        $landingPageId = Settings::getNoticeboardLandingPageId();
        if ($landingPageId === null || get_post_status($landingPageId) === false) {
            return $items;
        }

        $context = $this->resolveBreadcrumbContext($landingPageId);
        if ($context === null) {
            return $items;
        }

        $cptArchiveUrl = get_post_type_archive_link(Posttype::NOTICE_POST_TYPE);
        $cptArchiveUrl = is_string($cptArchiveUrl) ? $cptArchiveUrl : '';

        $matchAgainstArchiveUrl = $cptArchiveUrl !== ''
            ? $cptArchiveUrl
            : (get_permalink($landingPageId) ?: '');
        if ($matchAgainstArchiveUrl === '') {
            return $items;
        }

        $ordered = [];
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $ordered[] = ['key' => $key, 'item' => $item];
        }

        $archiveIndex = $this->findNativeArchiveCrumbIndex(
            $ordered,
            $matchAgainstArchiveUrl,
            $landingPageId
        );

        if ($archiveIndex === null) {
            return $items;
        }

        $hrefForLandingCrumb = $cptArchiveUrl !== ''
            ? $cptArchiveUrl
            : (get_permalink($landingPageId) ?: $matchAgainstArchiveUrl);

        $replacement = $this->buildReplacementSegment($landingPageId, $hrefForLandingCrumb, $context);

        $before = array_slice($ordered, 0, $archiveIndex);
        $after = array_slice($ordered, $archiveIndex + 1);

        $merged = array_merge($before, $replacement, $after);

        $result = [];
        foreach ($merged as $row) {
            $result[$row['key']] = $row['item'];
        }

        return $result;
    }

    /**
     * Municipio uses get_post_type_archive_link() for the crumb href. Some setups use the
     * Reading "page for post type" permalink instead; match either.
     *
     * @param list<array{key: int|string, item: array<string, mixed>}> $ordered
     */
    private function findNativeArchiveCrumbIndex(array $ordered, string $archiveUrl, int $landingPageId): ?int
    {
        $pagePermalink = get_permalink($landingPageId) ?: '';

        foreach ($ordered as $i => $row) {
            if (!isset($row['item']) || !is_array($row['item'])) {
                continue;
            }

            $item = $row['item'];
            $href = (string) ($item['href'] ?? $item['url'] ?? '');

            if ($href !== '') {
                foreach (array_filter([$archiveUrl, $pagePermalink]) as $candidate) {
                    if ($this->hrefMatchesArchive($href, $candidate)) {
                        return $i;
                    }
                    if ($this->urlsAreEquivalent($href, $candidate)) {
                        return $i;
                    }
                    if ($this->urlPathsAreEquivalent($href, $candidate)) {
                        return $i;
                    }
                }
            }

            if ($this->itemLabelMatchesNoticePostType($item)) {
                return $i;
            }
        }

        return null;
    }

    private function urlsAreEquivalent(string $a, string $b): bool
    {
        return untrailingslashit(esc_url_raw($a)) === untrailingslashit(esc_url_raw($b));
    }

    /**
     * Same path, different host/scheme, or one side is root-relative.
     */
    private function urlPathsAreEquivalent(string $a, string $b): bool
    {
        $pa = $this->urlPathOnly($a);
        $pb = $this->urlPathOnly($b);

        return $pa !== '' && $pb !== '' && untrailingslashit($pa) === untrailingslashit($pb);
    }

    private function urlPathOnly(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && ($url[0] === '/' || $url[0] === '.')) {
            $path = strtok($url, '?');

            return is_string($path) ? $path : '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    private function itemLabelMatchesNoticePostType(array $item): bool
    {
        if (!isset($item['label'])) {
            return false;
        }

        $label = trim(wp_strip_all_tags((string) $item['label']));
        if ($label === '') {
            return false;
        }

        $pto = get_post_type_object(Posttype::NOTICE_POST_TYPE);
        if (!$pto) {
            return false;
        }

        $candidates = array_filter([
            $pto->labels->name ?? '',
            $pto->label ?? '',
            $pto->labels->singular_name ?? '',
            $pto->labels->menu_name ?? '',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && strcasecmp($label, $candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return 'single'|'archive'|'landing_page'|null
     */
    private function resolveBreadcrumbContext(int $landingPageId): ?string
    {
        if (is_singular(Posttype::NOTICE_POST_TYPE)) {
            return self::CONTEXT_SINGLE;
        }

        if (is_post_type_archive(Posttype::NOTICE_POST_TYPE)) {
            return self::CONTEXT_ARCHIVE;
        }

        // Hub is often a normal Page (module + content) at a nested URL, not the CPT archive URL.
        if (is_page($landingPageId)) {
            return self::CONTEXT_LANDING_PAGE;
        }

        return null;
    }

    /**
     * @param 'single'|'archive'|'landing_page' $context
     *
     * @return list<array{key: int|string, item: array<string, mixed>}>
     */
    private function buildReplacementSegment(int $landingPageId, string $archiveUrl, string $context): array
    {
        $segment = [];
        $ancestors = array_reverse(get_post_ancestors($landingPageId));

        foreach ($ancestors as $ancestorId) {
            $ancestorId = (int) $ancestorId;
            if ($ancestorId < 1) {
                continue;
            }
            $segment[] = [
                'key' => $ancestorId,
                'item' => [
                    'label' => get_the_title($ancestorId),
                    'href' => get_permalink($ancestorId) ?: '',
                    'current' => false,
                    'icon' => 'chevron_right',
                ],
            ];
        }

        $landingHref = $archiveUrl;
        $landingCurrent = false;

        if ($context === self::CONTEXT_ARCHIVE) {
            $landingCurrent = true;
        } elseif ($context === self::CONTEXT_LANDING_PAGE) {
            $landingHref = get_permalink($landingPageId) ?: $archiveUrl;
            $landingCurrent = true;
        }

        $segment[] = [
            'key' => $landingPageId,
            'item' => [
                'label' => get_the_title($landingPageId),
                'href' => $landingHref,
                'current' => $landingCurrent,
                'icon' => 'chevron_right',
            ],
        ];

        return $segment;
    }

    private function hrefMatchesArchive(string $href, string $archiveUrl): bool
    {
        $a = untrailingslashit(esc_url_raw($href));
        $b = untrailingslashit(esc_url_raw($archiveUrl));
        if ($a === $b) {
            return true;
        }

        $basePath = wp_parse_url($b, PHP_URL_PATH);
        $hrefPath = wp_parse_url($a, PHP_URL_PATH);
        if (!is_string($basePath) || !is_string($hrefPath)) {
            return false;
        }

        $basePath = untrailingslashit($basePath);
        $hrefPath = untrailingslashit($hrefPath);

        if ($hrefPath === $basePath) {
            return true;
        }

        return (bool) preg_match(
            '#^' . preg_quote($basePath, '#') . '/page/\d+$#',
            $hrefPath
        );
    }
}
