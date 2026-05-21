<?php

namespace ModularityNoticeboard\CLI;

use ModularityNoticeboard\Admin\Settings;
use ModularityNoticeboard\Data\Posttype;
use WP_CLI;

/**
 * WP-CLI commands for managing noticeboard notices.
 */
class ArchiveNoticesCommand
{
    /**
     * Archive notices that have passed their archive date.
     *
     * Checks all published notices and archives those with an archive_date
     * that has passed. The archival action (unpublish or delete) is determined
     * by the noticeboard settings.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview what would be archived without making changes.
     *
     * ## EXAMPLES
     *
     *     # Archive notices with passed archive dates
     *     $ wp noticeboard archive
     *
     *     # Preview what would be archived
     *     $ wp noticeboard archive --dry-run
     *
     * @when after_wp_load
     */
    public function archive($args, $assoc_args)
    {
        $dryRun   = isset($assoc_args['dry-run']);
        $action   = Settings::getArchivalAction();
        $now      = current_datetime();
        $today    = $now->format('Y-m-d');
        $timezone = wp_timezone();

        WP_CLI::log(sprintf(
            'Checking notices for archival (action: %s)%s',
            $action,
            $dryRun ? ' [DRY RUN]' : ''
        ));

        $notices = get_posts([
            'post_type'      => Posttype::NOTICE_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'archive_date',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => 'archive_date',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
        ]);

        if (empty($notices)) {
            WP_CLI::success('No notices to archive.');
            return;
        }

        WP_CLI::log(sprintf('Found %d notice(s) to archive.', count($notices)));

        $archived = 0;
        $errors   = 0;

        foreach ($notices as $notice) {
            $archiveDate = get_field('archive_date', $notice->ID);
            $archiveTime = get_field('archive_time', $notice->ID);

            // If archive_time is set and the archive date is today, only archive
            // once the specified time has passed in the WordPress timezone.
            if ($archiveTime && $archiveDate === $today) {
                $archiveDt = new \DateTime($archiveDate . ' ' . $archiveTime, $timezone);
                if ($archiveDt > new \DateTime('now', $timezone)) {
                    continue;
                }
            }

            $archiveDisplay = $archiveDate . ($archiveTime ? ' ' . $archiveTime : '');

            if ($dryRun) {
                WP_CLI::log(sprintf(
                    '  Would %s: "%s" (ID: %d, archive date: %s)',
                    $action === 'delete' ? 'delete' : 'unpublish',
                    $notice->post_title,
                    $notice->ID,
                    $archiveDisplay
                ));
                $archived++;
                continue;
            }

            $result = $this->archiveNotice($notice, $action);

            if ($result) {
                WP_CLI::log(sprintf(
                    '  %s: "%s" (ID: %d, archive date: %s)',
                    $action === 'delete' ? 'Deleted' : 'Unpublished',
                    $notice->post_title,
                    $notice->ID,
                    $archiveDisplay
                ));
                $archived++;
            } else {
                WP_CLI::warning(sprintf(
                    '  Failed to %s: "%s" (ID: %d)',
                    $action === 'delete' ? 'delete' : 'unpublish',
                    $notice->post_title,
                    $notice->ID
                ));
                $errors++;
            }
        }

        if ($dryRun) {
            WP_CLI::success(sprintf(
                'Dry run complete. %d notice(s) would be %s.',
                $archived,
                $action === 'delete' ? 'deleted' : 'unpublished'
            ));
        } else {
            WP_CLI::success(sprintf(
                'Archival complete. %d notice(s) %s, %d error(s).',
                $archived,
                $action === 'delete' ? 'deleted' : 'unpublished',
                $errors
            ));
        }
    }

    /**
     * Archive a single notice based on the configured action.
     *
     * @param \WP_Post $notice The notice post to archive.
     * @param string   $action The archival action ('unpublish' or 'delete').
     * @return bool True on success, false on failure.
     */
    private function archiveNotice($notice, $action)
    {
        if ($action === 'delete') {
            $result = wp_delete_post($notice->ID, true);
            return $result !== false && $result !== null;
        }

        // Default action: unpublish (set to draft)
        $result = wp_update_post([
            'ID'          => $notice->ID,
            'post_status' => 'draft',
        ]);

        return !is_wp_error($result) && $result > 0;
    }

    /**
     * List all notices with their archive status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Options: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     # List all notices with archive dates
     *     $ wp noticeboard list
     *
     *     # Output as JSON
     *     $ wp noticeboard list --format=json
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args)
    {
        $format = $assoc_args['format'] ?? 'table';
        $today  = date('Y-m-d');

        $notices = get_posts([
            'post_type'      => Posttype::NOTICE_POST_TYPE,
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($notices)) {
            WP_CLI::log('No notices found.');
            return;
        }

        $data = [];

        foreach ($notices as $notice) {
            $archiveDate = get_field('archive_date', $notice->ID) ?: '-';
            $status      = 'active';

            if ($archiveDate !== '-' && $archiveDate <= $today && $notice->post_status === 'publish') {
                $status = 'pending archival';
            } elseif ($notice->post_status === 'draft') {
                $status = 'archived';
            }

            $data[] = [
                'ID'           => $notice->ID,
                'title'        => $notice->post_title,
                'post_status'  => $notice->post_status,
                'archive_date' => $archiveDate,
                'status'       => $status,
            ];
        }

        WP_CLI\Utils\format_items($format, $data, ['ID', 'title', 'post_status', 'archive_date', 'status']);
    }
}
