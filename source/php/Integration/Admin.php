<?php

namespace ModularityNoticeboard\Integration;

final class Admin
{
    public const PAGE = 'noticeboard-integrations';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_noticeboard_integrations', [$this, 'save']);
    }

    public static function url(): string
    {
        return admin_url('edit.php?post_type=noticeboard_notice&page=' . self::PAGE);
    }

    public function menu(): void
    {
        add_submenu_page('edit.php?post_type=noticeboard_notice', __('Noticeboard integrations', 'modularity-noticeboard'),
            __('Integrations', 'modularity-noticeboard'), 'manage_options', self::PAGE, [$this, 'render']);
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot manage noticeboard integrations.', 'modularity-noticeboard'), '', ['response' => 403]);
        }
        check_admin_referer('noticeboard_integrations');
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? wp_unslash($_POST['operation']) : '';
        $tokens = new Tokens();
        $result = null;
        if (in_array($operation, ['create', 'rotate'], true) && !is_ssl()) {
            wp_die(esc_html__('Use HTTPS to create or rotate tokens.', 'modularity-noticeboard'), '', ['response' => 403]);
        }
        if ($operation === 'nova') {
            update_option(NovaPublicationEndpoint::ENABLED_OPTION, !empty($_POST['enabled']) ? '1' : '0', false);
        } elseif ($operation === 'create') {
            $source = $this->text('source');
            $label = $this->text('label');
            $scopes = [];
            if (isset($_POST['scopes']) && is_array($_POST['scopes'])) {
                foreach ($_POST['scopes'] as $scope) {
                    if (is_string($scope)) {
                        $scopes[] = sanitize_key(wp_unslash($scope));
                    }
                }
            }
            $types = $this->ids('type_ids');
            $groups = $this->ids('group_ids');
            if ($types === null || $groups === null) {
                $result = Validation::error('noticeboard_invalid_terms', 'Select valid term IDs.');
            } else {
                $result = $tokens->create($source, $label, $scopes, $types, $groups);
            }
        } elseif (in_array($operation, ['rotate', 'revoke'], true)) {
            $id = $this->text('token_id');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
                $result = Validation::error('noticeboard_invalid_token', 'Invalid token ID.');
            } elseif ($operation === 'rotate') {
                $result = $tokens->rotate($id);
            } elseif (!$tokens->revoke($id)) {
                $result = Validation::error('noticeboard_token_save_failed', 'Token could not be revoked.', 500);
            }
        } else {
            $result = Validation::error('noticeboard_invalid_operation', 'Unknown operation.');
        }
        if ($result !== null) {
            // Never persist plaintext tokens, even to a transient or redirected query string.
            nocache_headers();
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex');
            header('Content-Type: text/html; charset=' . get_option('blog_charset'));
            echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__('Noticeboard token', 'modularity-noticeboard') . '</title></head><body>';
            if (is_wp_error($result)) {
                echo '<h1>' . esc_html__('Could not save integration', 'modularity-noticeboard') . '</h1><p>' . esc_html($result->get_error_message()) . '</p>';
            } else {
                echo '<h1>' . esc_html__('Copy your token now', 'modularity-noticeboard') . '</h1><p>' . esc_html__('It will not be displayed again. Rotation invalidates the previous token immediately.', 'modularity-noticeboard') . '</p><pre>' . esc_html($result['token']) . '</pre>';
            }
            echo '<p><a href="' . esc_url(self::url()) . '">' . esc_html__('Back to integrations', 'modularity-noticeboard') . '</a></p></body></html>';
            exit;
        }
        wp_safe_redirect(self::url());
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $nova = NovaPublicationEndpoint::getStatus();
        echo '<div class="wrap"><h1>' . esc_html__('Noticeboard integrations', 'modularity-noticeboard') . '</h1>';
        echo '<h2>Sokigo Nova</h2><p>' . esc_html__('Publication endpoint:', 'modularity-noticeboard') . ' <code>' . esc_html($nova['endpoint_url']) . '</code></p>';
        echo '<p>' . esc_html__('Credentials configured:', 'modularity-noticeboard') . ' ' . esc_html($nova['configured'] ? __('Yes', 'modularity-noticeboard') : __('No', 'modularity-noticeboard')) . '</p>';
        echo '<p>' . esc_html__('Route owner:', 'modularity-noticeboard') . ' ' . esc_html($nova['owner']) . '</p>';
        echo '<p>' . esc_html__('Define SOKIGO_NOVA_PUBLISH_USERNAME and SOKIGO_NOVA_PUBLISH_PASSWORD in WordPress configuration. Requests require HTTPS.', 'modularity-noticeboard') . '</p>';
        if ($nova['owner'] === 'external') {
            echo '<p><strong>' . esc_html__('Another plugin currently owns this route. The switch below affects only the shared adapter; remove the old registration when deploying the coordinated migration.', 'modularity-noticeboard') . '</strong></p>';
        }
        $this->form('nova');
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked($nova['enabled'], true, false) . '> ' . esc_html__('Enable shared Nova adapter', 'modularity-noticeboard') . '</label>';
        submit_button(__('Save Nova configuration', 'modularity-noticeboard'));
        echo '</form><h2>' . esc_html__('General publication API', 'modularity-noticeboard') . '</h2><p><code>' . esc_html(rest_url(RestApi::REST_NAMESPACE . '/notices/{external_id}')) . '</code></p>';
        echo '<p>' . esc_html__('Tokens are restricted to a source and selected operations and terms. Tokens with the same source share ownership. Empty term selections permit no terms.', 'modularity-noticeboard') . '</p>';
        echo '<h3>' . esc_html__('Create token', 'modularity-noticeboard') . '</h3>';
        $this->form('create');
        echo '<p><label>' . esc_html__('Source slug', 'modularity-noticeboard') . ' <input name="source" pattern="[a-z0-9][a-z0-9_-]{0,63}" maxlength="64" required></label> ' . esc_html__('Use a stable slug; nova is reserved.', 'modularity-noticeboard') . '</p>';
        echo '<p><label>' . esc_html__('Label', 'modularity-noticeboard') . ' <input name="label" maxlength="200" required></label></p>';
        foreach (['create' => __('Create', 'modularity-noticeboard'), 'update' => __('Update', 'modularity-noticeboard'), 'withdraw' => __('Withdraw', 'modularity-noticeboard')] as $scope => $label) {
            echo '<label><input type="checkbox" name="scopes[]" value="' . esc_attr($scope) . '" checked> ' . esc_html($label) . '</label> ';
        }
        foreach (['noticeboard_notice_type' => 'type_ids', 'notice_group' => 'group_ids'] as $taxonomy => $field) {
            echo '<p><label>' . esc_html($field === 'type_ids' ? __('Allowed notice types', 'modularity-noticeboard') : __('Allowed groups', 'modularity-noticeboard')) . '<br><select name="' . esc_attr($field) . '[]" multiple size="5">';
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . ' (' . esc_html($term->term_id) . ')</option>';
                }
            }
            echo '</select></label></p>';
        }
        submit_button(__('Create token', 'modularity-noticeboard'));
        echo '</form><h3>' . esc_html__('Existing tokens', 'modularity-noticeboard') . '</h3>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach ([__('Label / source', 'modularity-noticeboard'), __('Permissions', 'modularity-noticeboard'), __('Created (UTC)', 'modularity-noticeboard'), __('Actions', 'modularity-noticeboard')] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ((new Tokens())->listing() as $row) {
            echo '<tr><td>' . esc_html($row['label']) . '<br><code>' . esc_html($row['source']) . '</code></td><td><code>' . esc_html($row['policy']) . '</code></td><td>' . esc_html($row['created_at']) . '</td><td>';
            if ((int) $row['revoked']) {
                echo esc_html__('Revoked', 'modularity-noticeboard');
            } else {
                foreach (['rotate' => __('Rotate', 'modularity-noticeboard'), 'revoke' => __('Revoke', 'modularity-noticeboard')] as $operation => $label) {
                    $this->form($operation);
                    echo '<input type="hidden" name="token_id" value="' . esc_attr($row['token_id']) . '">';
                    submit_button($label, 'secondary', 'submit', false);
                    echo '</form> ';
                }
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function form(string $operation): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('noticeboard_integrations');
        echo '<input type="hidden" name="action" value="noticeboard_integrations"><input type="hidden" name="operation" value="' . esc_attr($operation) . '">';
    }

    private function text(string $name): string
    {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
    }

    private function ids(string $name): ?array
    {
        if (!isset($_POST[$name])) {
            return [];
        }
        if (!is_array($_POST[$name])) {
            return null;
        }
        $ids = [];
        foreach ($_POST[$name] as $id) {
            if (!is_string($id) || !ctype_digit($id) || (int) $id <= 0) {
                return null;
            }
            $ids[] = (int) $id;
        }
        return $ids;
    }
}
