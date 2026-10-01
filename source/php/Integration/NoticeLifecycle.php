<?php

namespace ModularityNoticeboard\Integration;

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
}
