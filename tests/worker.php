<?php
require __DIR__ . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
$request = new WP_REST_Request($input['method'], '/noticeboard/v1/notices/' . rawurlencode($input['id']));
$request->set_header('authorization', 'Bearer ' . $input['token']);
if ($input['method'] === 'PUT') {
    $request->set_header('content-type', 'application/json');
    $request->set_body(wp_json_encode($input['data']));
    add_filter('wp_insert_post_data', static function ($data) {
        if ($data['post_type'] === 'noticeboard_notice') {
            usleep(200000);
        }
        return $data;
    });
}
$response = rest_get_server()->dispatch($request);
// No token or request payload in worker output.
echo wp_json_encode(['status' => $response->get_status(), 'data' => $response->get_data()]);
