<?php

namespace ModularityNoticeboard\Integration;

final class Admin
{
    public const PAGE = 'noticeboard-integrations';
    private $pageHook = null;

    public static function url(): string
    {
        return admin_url('edit.php?post_type=noticeboard_notice&page=' . self::PAGE);
    }

    public function menu(): void
    {
        $this->pageHook = add_submenu_page('edit.php?post_type=noticeboard_notice', __('Noticeboard integrations', 'modularity-noticeboard'),
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
                $result = Validation::error('noticeboard_invalid_terms', __('Select valid term IDs.', 'modularity-noticeboard'));
            } else {
                $result = $tokens->create($source, $label, $scopes, $types, $groups);
            }
        } elseif (in_array($operation, ['rotate', 'revoke', 'delete'], true)) {
            $id = $this->text('token_id');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
                $result = Validation::error('noticeboard_invalid_token', __('Invalid token ID.', 'modularity-noticeboard'));
            } elseif ($operation === 'rotate') {
                $result = $tokens->rotate($id);
            } elseif ($operation === 'delete') {
                if (!$tokens->deleteRevoked($id)) {
                    $result = Validation::error('noticeboard_token_delete_failed', __('Token could not be deleted. Only revoked tokens can be deleted.', 'modularity-noticeboard'));
                }
            } elseif (!$tokens->revoke($id)) {
                $result = Validation::error('noticeboard_token_save_failed', __('Token could not be revoked.', 'modularity-noticeboard'), 500);
            }
        } else {
            $result = Validation::error('noticeboard_invalid_operation', __('Unknown operation.', 'modularity-noticeboard'));
        }
        if ($result !== null) {
            // Never persist plaintext tokens, even to a transient or redirected query string.
            nocache_headers();
            header('Referrer-Policy: no-referrer');
            header('X-Robots-Tag: noindex');
            if (is_wp_error($result)) {
                $message = '<p>' . esc_html($result->get_error_message()) . '</p>';
                $title = __('Could not save integration', 'modularity-noticeboard');
                $status = (int) ($result->get_error_data()['status'] ?? 400);
            } else {
                $title = __('Copy your token now', 'modularity-noticeboard');
                $message = '<p>' . esc_html__('It will not be displayed again. Rotation invalidates the previous token immediately.', 'modularity-noticeboard') . '</p>';
                $message .= '<p><textarea readonly rows="3" style="width:100%;box-sizing:border-box" aria-label="' . esc_attr__('Noticeboard token', 'modularity-noticeboard') . '">' . esc_textarea($result['token']) . '</textarea></p>';
                $status = 200;
            }
            $message .= '<p><a class="button" href="' . esc_url(self::url()) . '">' . esc_html__('Back to integrations', 'modularity-noticeboard') . '</a></p>';
            wp_die($message, $title, ['response' => $status]);
        }
        wp_safe_redirect(self::url());
        exit;
    }

    public function enqueueStyles(string $hook): void
    {
        if ($hook !== $this->pageHook) {
            return;
        }
        wp_enqueue_style('noticeboard-integrations', MODULARITY_NOTICEBOARD_URL . '/source/css/admin-integrations.css', [],
            (string) filemtime(MODULARITY_NOTICEBOARD_PATH . 'source/css/admin-integrations.css'));
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap nb-integrations">
            <h1><?php esc_html_e('Noticeboard integrations', 'modularity-noticeboard'); ?></h1>
            <p class="nb-integrations-intro"><?php esc_html_e('Connect external systems to publish notices on your noticeboard.', 'modularity-noticeboard'); ?></p>

            <section class="nb-integration-card" aria-labelledby="nb-api-heading">
                <div class="nb-integration-card-header">
                    <h2 id="nb-api-heading"><?php esc_html_e('General publication API', 'modularity-noticeboard'); ?></h2>
                    <p><?php esc_html_e('Create a token for each integration and choose which notices it can manage.', 'modularity-noticeboard'); ?></p>
                </div>
                <div class="nb-integration-card-body">
                    <span class="nb-integration-label"><?php esc_html_e('API endpoint', 'modularity-noticeboard'); ?></span>
                    <code class="nb-integration-endpoint"><?php echo esc_html(rest_url(RestApi::REST_NAMESPACE . '/notices/{external_id}')); ?></code>
                    <p class="description"><?php esc_html_e('Requests require HTTPS and a bearer token. Each external notice has a unique ID within its source.', 'modularity-noticeboard'); ?></p>
                    <p><a class="button button-secondary" href="https://github.com/Considbrs-Webdev/modularity-noticeboard/blob/main/docs/integrations.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e('API documentation and request examples', 'modularity-noticeboard'); ?></a></p>
                </div>
            </section>

            <div class="nb-integrations-grid">
                <section class="nb-integration-card" aria-labelledby="nb-create-heading">
                    <div class="nb-integration-card-header">
                        <h2 id="nb-create-heading"><?php esc_html_e('Create token', 'modularity-noticeboard'); ?></h2>
                        <p><?php esc_html_e('The token is displayed once after creation. Store it securely.', 'modularity-noticeboard'); ?></p>
                    </div>
                    <div class="nb-integration-card-body">
                        <?php $this->form('create'); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="nb-token-label"><?php esc_html_e('Token name', 'modularity-noticeboard'); ?></label></th>
                                <td><input id="nb-token-label" class="regular-text" name="label" maxlength="200" required>
                                    <p class="description"><?php esc_html_e('A name that helps you recognise the integration.', 'modularity-noticeboard'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="nb-token-source"><?php esc_html_e('Source identifier', 'modularity-noticeboard'); ?></label></th>
                                <td><input id="nb-token-source" class="regular-text code" name="source" pattern="[a-z0-9][a-z0-9_-]{0,63}" maxlength="64" required aria-describedby="nb-source-help">
                                    <p id="nb-source-help" class="description"><?php esc_html_e('Use a stable identifier, for example building-permits. Tokens with the same identifier manage the same notices. The identifier nova is reserved.', 'modularity-noticeboard'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Permissions', 'modularity-noticeboard'); ?></th>
                                <td><fieldset class="nb-integration-checks">
                                    <legend class="screen-reader-text"><?php esc_html_e('Permissions', 'modularity-noticeboard'); ?></legend>
                                    <?php foreach ($this->scopeLabels() as $scope => $label) : ?>
                                        <label><input type="checkbox" name="scopes[]" value="<?php echo esc_attr($scope); ?>" checked> <?php echo esc_html($label); ?></label>
                                    <?php endforeach; ?>
                                </fieldset></td>
                            </tr>
                            <?php foreach (['noticeboard_notice_type' => 'type_ids', 'notice_group' => 'group_ids'] as $taxonomy => $field) : ?>
                                <tr>
                                    <th scope="row" id="nb-<?php echo esc_attr($field); ?>-label"><?php echo esc_html($field === 'type_ids' ? __('Allowed notice types', 'modularity-noticeboard') : __('Allowed groups', 'modularity-noticeboard')); ?></th>
                                    <td>
                                        <fieldset class="nb-integration-terms" aria-labelledby="nb-<?php echo esc_attr($field); ?>-label">
                                            <?php $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]); ?>
                                            <?php if (!is_wp_error($terms) && $terms) : ?>
                                                <?php foreach ($terms as $term) : ?>
                                                    <label><input type="checkbox" name="<?php echo esc_attr($field); ?>[]" value="<?php echo esc_attr($term->term_id); ?>"> <?php echo esc_html($term->name); ?> <span class="nb-integration-term-id">(#<?php echo esc_html($term->term_id); ?>)</span></label>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <p class="description"><?php esc_html_e('No terms available. Add them to the noticeboard first.', 'modularity-noticeboard'); ?></p>
                                            <?php endif; ?>
                                        </fieldset>
                                        <p class="description"><?php esc_html_e('Only selected terms are allowed. Leave empty to allow none.', 'modularity-noticeboard'); ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php submit_button(__('Create token', 'modularity-noticeboard')); ?>
                        </form>
                    </div>
                </section>

                <section class="nb-integration-card" aria-labelledby="nb-tokens-heading">
                    <div class="nb-integration-card-header">
                        <h2 id="nb-tokens-heading"><?php esc_html_e('Existing tokens', 'modularity-noticeboard'); ?></h2>
                        <p><?php esc_html_e('Rotate a token to replace its secret, or revoke it to stop access.', 'modularity-noticeboard'); ?></p>
                    </div>
                    <div class="nb-integration-card-body">
                        <?php $rows = (new Tokens())->listing(); ?>
                        <?php if (!$rows) : ?>
                            <div class="nb-integration-empty">
                                <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                                <h3><?php esc_html_e('No tokens yet', 'modularity-noticeboard'); ?></h3>
                                <p class="description"><?php esc_html_e('Create a token to connect your first integration.', 'modularity-noticeboard'); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="nb-integration-table-wrap">
                                <table class="widefat striped">
                                    <thead><tr>
                                        <th scope="col"><?php esc_html_e('Integration', 'modularity-noticeboard'); ?></th>
                                        <th scope="col"><?php esc_html_e('Access', 'modularity-noticeboard'); ?></th>
                                        <th scope="col"><?php esc_html_e('Actions', 'modularity-noticeboard'); ?></th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ($rows as $row) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($row['label']); ?></strong><br><code><?php echo esc_html($row['source']); ?></code>
                                                <p class="description"><?php /* translators: %s: Creation date and time in the site's timezone. */ echo esc_html(sprintf(__('Created: %s', 'modularity-noticeboard'), get_date_from_gmt($row['created_at'], get_option('date_format') . ' ' . get_option('time_format')))); ?></p>
                                            </td>
                                            <td><?php $this->renderPolicy((string) $row['policy']); ?></td>
                                            <td>
                                                <?php if ((int) $row['revoked']) : ?>
                                                    <div class="nb-integration-actions">
                                                        <span class="nb-integration-status"><?php esc_html_e('Revoked', 'modularity-noticeboard'); ?></span>
                                                        <?php $this->form('delete'); ?>
                                                        <input type="hidden" name="token_id" value="<?php echo esc_attr($row['token_id']); ?>">
                                                        <button type="submit" class="button button-secondary" aria-label="<?php /* translators: %s: Integration name. */ echo esc_attr(sprintf(__('Delete revoked token: %s', 'modularity-noticeboard'), $row['label'])); ?>" onclick="<?php echo esc_attr('return confirm(' . wp_json_encode(__('Permanently delete this revoked token? Published notices will not be deleted.', 'modularity-noticeboard')) . ');'); ?>"><?php esc_html_e('Delete token', 'modularity-noticeboard'); ?></button>
                                                        </form>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="nb-integration-actions">
                                                        <?php foreach (['rotate' => __('Rotate', 'modularity-noticeboard'), 'revoke' => __('Revoke', 'modularity-noticeboard')] as $operation => $label) : ?>
                                                            <?php $this->form($operation); ?>
                                                            <input type="hidden" name="token_id" value="<?php echo esc_attr($row['token_id']); ?>">
                                                            <button type="submit" class="button button-secondary" aria-label="<?php /* translators: 1: Token action, 2: Integration name. */ echo esc_attr(sprintf(__('%1$s token: %2$s', 'modularity-noticeboard'), $label, $row['label'])); ?>"><?php echo esc_html($label); ?></button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <?php $nova = NovaPublicationEndpoint::getStatus(); ?>
            <section class="nb-integration-card" aria-labelledby="nb-nova-heading">
                <div class="nb-integration-card-header">
                    <h2 id="nb-nova-heading">Sokigo Nova</h2>
                    <p><?php esc_html_e('Receive building permit notices and decisions from Sokigo Nova.', 'modularity-noticeboard'); ?></p>
                </div>
                <div class="nb-integration-card-body">
                    <?php if ($nova['owner'] === 'external') : ?>
                        <div class="notice notice-info inline"><p><?php esc_html_e('Another plugin currently handles Nova publications. This setting only controls the noticeboard integration.', 'modularity-noticeboard'); ?></p></div>
                    <?php endif; ?>
                    <?php $this->form('nova'); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Status', 'modularity-noticeboard'); ?></th>
                            <td><span class="nb-integration-status <?php echo $nova['active'] ? 'is-active' : ''; ?>"><?php echo esc_html($nova['active'] ? __('Active', 'modularity-noticeboard') : __('Inactive', 'modularity-noticeboard')); ?></span></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Publication endpoint', 'modularity-noticeboard'); ?></th>
                            <td><code class="nb-integration-endpoint"><?php echo esc_html($nova['endpoint_url']); ?></code></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Credentials configured', 'modularity-noticeboard'); ?></th>
                            <td><?php echo esc_html($nova['configured'] ? __('Yes', 'modularity-noticeboard') : __('No', 'modularity-noticeboard')); ?>
                                <p class="description"><?php esc_html_e('Define SOKIGO_NOVA_PUBLISH_USERNAME and SOKIGO_NOVA_PUBLISH_PASSWORD in WordPress configuration. Requests require HTTPS.', 'modularity-noticeboard'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Handled by', 'modularity-noticeboard'); ?></th>
                            <td><?php echo esc_html(['noticeboard' => __('Noticeboard plugin', 'modularity-noticeboard'), 'external' => __('Another plugin', 'modularity-noticeboard'), 'none' => __('No active endpoint', 'modularity-noticeboard')][$nova['owner']]); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Enable integration', 'modularity-noticeboard'); ?></th>
                            <td><label><input type="checkbox" name="enabled" value="1" <?php checked($nova['enabled']); ?>> <?php esc_html_e('Enable shared Nova adapter', 'modularity-noticeboard'); ?></label></td></tr>
                    </table>
                    <?php submit_button(__('Save Nova configuration', 'modularity-noticeboard')); ?>
                    </form>
                </div>
            </section>
        </div>
        <?php
    }

    private function scopeLabels(): array
    {
        return ['create' => __('Create', 'modularity-noticeboard'), 'update' => __('Update', 'modularity-noticeboard'), 'withdraw' => __('Withdraw', 'modularity-noticeboard')];
    }

    private function renderPolicy(string $json): void
    {
        $policy = json_decode($json, true) ?: [];
        $labels = $this->scopeLabels();
        $scopes = array_intersect_key($labels, array_flip($policy['scopes'] ?? []));
        echo esc_html($scopes ? implode(', ', $scopes) : __('None', 'modularity-noticeboard'));
        foreach (['type_ids' => 'noticeboard_notice_type', 'group_ids' => 'notice_group'] as $field => $taxonomy) {
            $names = [];
            foreach ($policy[$field] ?? [] as $id) {
                $term = get_term((int) $id, $taxonomy);
                $names[] = $term && !is_wp_error($term) ? $term->name : '#' . (int) $id;
            }
            echo '<p class="description"><strong>' . esc_html($field === 'type_ids' ? __('Types:', 'modularity-noticeboard') : __('Groups:', 'modularity-noticeboard')) . '</strong> ' . esc_html($names ? implode(', ', $names) : __('None', 'modularity-noticeboard')) . '</p>';
        }
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
