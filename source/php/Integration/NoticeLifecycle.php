<?php

namespace ModularityNoticeboard\Integration;

use ModularityNoticeboard\Data\Posttype;

final class NoticeLifecycle
{
    public static function archiveTimestamp(int $postId): ?int
    {
        $date = (string) get_post_meta($postId, 'archive_date', true);
        if ($date === '') {
            return null;
        }
        // Accept ACF's Ymd storage and the older endpoint's Y-m-d fallback.
        $format = preg_match('/^\d{8}$/D', $date) ? '!Ymd H:i' : '!Y-m-d H:i';
        $time = (string) get_post_meta($postId, 'archive_time', true);
        $canonical = (string) get_post_meta($postId, '_noticeboard_archive_at', true);
        // Preserve the actual instant during the repeated hour at daylight-saving transitions.
        // Honour local field edits when they no longer match the imported timestamp.
        if (Validation::timestamp($canonical)
            && str_replace('-', '', $date) === wp_date('Ymd', (int) $canonical, wp_timezone())
            && $time === wp_date('H:i', (int) $canonical, wp_timezone())) {
            return (int) $canonical;
        }
        $dt = \DateTimeImmutable::createFromFormat($format, $date . ' ' . ($time ?: '00:00'), wp_timezone());
        return $dt ? $dt->getTimestamp() : null;
    }

    /** Prevent a delayed WordPress publication job from publishing an expired import. */
    public static function scheduledPublish(int $postId): void
    {
        if (get_post_type($postId) !== Posttype::NOTICE_POST_TYPE
            || get_post_meta($postId, NoticeWriter::SOURCE_META, true) === '') {
            check_and_publish_future_post($postId);
            return;
        }
        $source = (string) get_post_meta($postId, NoticeWriter::SOURCE_META, true);
        $id = (string) get_post_meta($postId, NoticeWriter::ID_META, true);
        $key = Storage::key($source, $id);
        if (!Storage::lock($key)) {
            wp_schedule_single_event(time() + 60, 'publish_future_post', [$postId]);
            return;
        }
        try {
            $row = Storage::identity($key);
            $archive = self::archiveTimestamp($postId);
            if (($row && in_array($row['state'], ['withdrawn', 'expired', 'pending', 'creating'], true))
                || ($archive !== null && $archive <= time())) {
                wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
                if ($row && $row['state'] === 'active') {
                    Storage::saveIdentity($key, $source, $id, $postId, 'expired');
                }
            } else {
                check_and_publish_future_post($postId);
            }
        } finally {
            Storage::unlock($key);
        }
    }
}
