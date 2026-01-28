<?php
namespace ModularityNoticeboard\Frontend;

use ModularityNoticeboard\Admin\Settings;
use ModularityNoticeboard\Data\Posttype;

class Breadcrumbs 
{
    private string $breadcrumbTitle;

    public function __construct()
    {
        // Fix breadcrumbs if noticeboard archive is custom page
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'maybeInsertArchiveIntoBreadcrumbs'], 20);

        add_action('acf/init', function() {
            $this->breadcrumbTitle = Settings::getBreadcumbTitle();

            if (Settings::useCustomArchivePage() === false && !empty($this->breadcrumbTitle)) {
                add_filter('Municipio/Breadcrumbs/Items', [$this, 'maybeEditBreadcrumbsTitle'], 20);
            }
        });
        
    }

    public function maybeEditBreadcrumbsTitle($items): array|null
    {
        if (!is_array($items)) {
            return $items;
        }
        
        if (!is_singular(Posttype::NOTICE_POST_TYPE) && !is_archive(Posttype::NOTICE_POST_TYPE)) {
            return $items;
        }

        if (is_singular(Posttype::NOTICE_POST_TYPE)) {
            $archiveUrl = get_post_type_archive_link(Posttype::NOTICE_POST_TYPE);

            // Find the breadcrumb item for the archive page and update its title
            foreach ($items as &$item) {
                if (isset($item['href']) && $item['href'] === $archiveUrl) {
                    $item['label'] = $this->breadcrumbTitle;
                    break;
                }
            }
        } else {
            // Archive - replace last breadcrumb item's label with 'test'
            $keys = array_keys($items);
            $lastKey = end($keys);
            if ($lastKey !== false && isset($items[$lastKey])) {
                $items[$lastKey]['label'] = $this->breadcrumbTitle;
            }
        }
        

        return $items;
    }

    /**
     * Insert archive page and its ancestors into breadcrumb items after the first item.
     *
     * @param array|null $items
     * @return array|null Modified breadcrumb items or null if no changes made
     */
    public function maybeInsertArchiveIntoBreadcrumbs($items): array|null
    {
        if (!is_array($items)) {
            return $items;
        }

        if (Settings::useCustomArchivePage() === false) {
            return $items;
        }

        if (!is_singular(Posttype::NOTICE_POST_TYPE)) {
            return $items;
        }

        $customArchiveLinkId = Settings::getMainPageId();
        if (empty($customArchiveLinkId)) {
            return $items;
        }

        // Ensure we have the post ID
        $archiveId = (int) $customArchiveLinkId;
        if ($archiveId <= 0 || get_post_status($archiveId) === false) {
            return $items;
        }

        // Get ancestors from top-most down to direct parent
        $ancestors = array_reverse(get_post_ancestors($archiveId));

        // If no ancestors and archive is already present, nothing to do
        if (empty($ancestors) && array_key_exists($archiveId, $items)) {
            return $items;
        }

        // Build insertion items keyed by post ID, skipping duplicates
        $insertItems = [];
        foreach ($ancestors as $ancestorId) {
            if (array_key_exists($ancestorId, $items)) {
                continue;
            }

            $insertItems[$ancestorId] = [
                'label' => get_the_title($ancestorId),
                'href' => get_permalink($ancestorId),
                'current' => false,
                'icon' => 'chevron_right',
            ];
        }

        // Add archive page itself if not already in items
        if (!array_key_exists($archiveId, $items)) {
            $insertItems[$archiveId] = [
                'label' => get_the_title($archiveId),
                'href' => get_permalink($archiveId),
                'current' => false,
                'icon' => 'chevron_right',
            ];
        }

        if (empty($insertItems)) {
            return $items;
        }

        // Rebuild items: keep first element, insert new items, then remaining
        $newItems = [];
        $keys = array_keys($items);
        $firstKey = array_shift($keys);

        // Add first original item
        $newItems[$firstKey] = $items[$firstKey];

        // Add inserted ancestors + archive
        foreach ($insertItems as $k => $v) {
            $newItems[$k] = $v;
        }

        // Add remaining original items in original order
        foreach ($keys as $k) {
            if (isset($items[$k])) {
                $newItems[$k] = $items[$k];
            }
        }

        return $newItems;
    }
}