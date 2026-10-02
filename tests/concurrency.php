<?php
require __DIR__ . '/bootstrap.php';

use ModularityNoticeboard\Integration\Storage;
use ModularityNoticeboard\Integration\Tokens;

function startWorker(string $method, string $id, string $token, array $data): array
{
    $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start worker');
    }
    fwrite($pipes[0], wp_json_encode(compact('method', 'id', 'token', 'data')));
    fclose($pipes[0]);
    return [$process, $pipes];
}
function finishWorker(array $worker): array
{
    [$process, $pipes] = $worker;
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    $response = json_decode($output, true);
    if ($exit || !is_array($response)) {
        throw new RuntimeException('Worker failed: ' . $error);
    }
    return $response;
}
try {
    $source = 'concurrent-' . bin2hex(random_bytes(4));
    $type = wp_insert_term($source, 'noticeboard_notice_type');
    $issued = is_wp_error($type) ? $type : (new Tokens())->create($source, 'Concurrent test', ['create', 'update', 'withdraw'], [(int) $type['term_id']], []);
    if (is_wp_error($issued)) {
        throw new RuntimeException('Could not issue test token');
    }
    $data = ['title' => 'Concurrent notice', 'content' => 'Body', 'publish_at' => time() - 300, 'archive_at' => time() + 86400,
        'type_ids' => [(int) $type['term_id']]];
    $workers = [];
    for ($i = 0; $i < 6; ++$i) {
        $workers[] = startWorker('PUT', 'same-id', $issued['token'], $data);
    }
    $ids = [];
    foreach ($workers as $worker) {
        $response = finishWorker($worker);
        if (!in_array($response['status'], [200, 201], true)) {
            throw new RuntimeException('Concurrent PUT failed: ' . wp_json_encode($response));
        }
        $ids[] = $response['data']['post_id'];
    }
    if (count(array_unique($ids)) !== 1) {
        throw new RuntimeException('Concurrent deliveries created duplicate IDs');
    }
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_noticeboard_integration_source', $source));
    if ((int) $count !== 1) {
        throw new RuntimeException('Duplicate imported posts found');
    }
    $workers = [startWorker('DELETE', 'same-id', $issued['token'], $data)];
    for ($i = 0; $i < 4; ++$i) {
        $workers[] = startWorker('PUT', 'same-id', $issued['token'], $data);
    }
    foreach ($workers as $worker) {
        $response = finishWorker($worker);
        if (!in_array($response['status'], [200, 409], true)) {
            throw new RuntimeException('Concurrent withdrawal/update failed');
        }
    }
    clean_post_cache($ids[0]);
    if (get_post_status($ids[0]) !== 'draft' || Storage::identity(Storage::key($source, 'same-id'))['state'] !== 'withdrawn') {
        throw new RuntimeException('Concurrent retry undid withdrawal');
    }
    $key = Storage::key($source, 'busy-id');
    if (!Storage::lock($key)) {
        throw new RuntimeException('Could not acquire lock fixture');
    }
    try {
        $response = finishWorker(startWorker('PUT', 'busy-id', $issued['token'], $data));
        if ($response['status'] !== 503) {
            throw new RuntimeException('Busy identity did not fail closed');
        }
    } finally {
        Storage::unlock($key);
    }
    $response = finishWorker(startWorker('PUT', 'busy-id', $issued['token'], $data));
    if ($response['status'] !== 201) {
        throw new RuntimeException('Retry after lock release failed');
    }
    echo "PASS: six simultaneous upserts, concurrent withdrawal/update, lock timeout and retry\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
