<?php
// Invoke through `wp eval-file`, with the disposable WordPress installation selected.
require __DIR__ . '/bootstrap.php';

use ModularityNoticeboard\Admin\Settings;
use ModularityNoticeboard\CLI\ArchiveNoticesCommand;
use ModularityNoticeboard\Integration\NoticeWriter;
use ModularityNoticeboard\Integration\Storage;

if (!defined('WP_CLI') || !WP_CLI || !function_exists('update_field')) {
    throw new RuntimeException('Run this archival test through WP-CLI with ACF loaded.');
}
require_once dirname(__DIR__) . '/source/php/AcfFields/php/notice-general-settings.php';
$writer = new NoticeWriter();
$source = 'archive-' . bin2hex(random_bytes(4));
$data = ['title' => 'Archival test notice', 'content' => 'Body', 'publish_at' => time() - 7200, 'archive_at' => time() + 3600];
$command = new ArchiveNoticesCommand();
foreach (['unpublish', 'delete'] as $action) {
    update_field('field_697a69b88e075', $action, Settings::OPTION_PAGE_SLUG);
    $result = $writer->upsert($source, $action, $data, ['create', 'update']);
    if (is_wp_error($result)) {
        throw new RuntimeException('Could not create archival fixture');
    }
    $postId = $result['post_id'];
    $archiveAt = time() - 3600;
    update_field('field_69679a808b9be', wp_date('Ymd', $archiveAt), $postId);
    update_field('field_6a0eeddaa1aa7', wp_date('H:i', $archiveAt), $postId);
    $command->archive([], ['dry-run' => true]);
    if (get_post_status($postId) !== 'publish' || Storage::identity(Storage::key($source, $action))['state'] !== 'active') {
        throw new RuntimeException('Archival dry-run changed a notice');
    }
    $command->archive([], []);
    if (($action === 'unpublish' && get_post_status($postId) !== 'draft')
        || ($action === 'delete' && get_post($postId))) {
        throw new RuntimeException('Archival action did not take effect');
    }
    if (Storage::identity(Storage::key($source, $action))['state'] !== 'expired') {
        throw new RuntimeException('Archival identity not closed');
    }
    $retry = $writer->upsert($source, $action, $data, ['create', 'update']);
    if (!is_wp_error($retry) || $retry->get_error_data()['status'] !== 409) {
        throw new RuntimeException('Retry recreated an archived notice');
    }
}
update_field('field_697a69b88e075', 'unpublish', Settings::OPTION_PAGE_SLUG);
WP_CLI::success('PASS: archival dry-run, unpublish/delete policies, and retry protection.');
