<?php

namespace ModularityNoticeboard\Integration;

/** Durable identities/tombstones and credentials. Tables are per WordPress site. */
final class Storage
{
    // Version 2 only re-saves the schema marker as autoloaded, avoiding a query per request.
    private const VERSION = '2';

    public static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'noticeboard_' . $suffix;
    }

    public static function install(): void
    {
        if (get_option('noticeboard_integration_schema') === self::VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $identities = self::table('identities');
        $tokens = self::table('tokens');
        dbDelta("CREATE TABLE $identities (
            identity_key varchar(64) NOT NULL,
            source varchar(64) NOT NULL,
            external_id varchar(255) NOT NULL,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            state varchar(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (identity_key)
        ) $charset;");
        dbDelta("CREATE TABLE $tokens (
            token_id varchar(32) NOT NULL,
            source varchar(64) NOT NULL,
            label varchar(200) NOT NULL,
            token_hash varchar(64) NOT NULL,
            policy longtext NOT NULL,
            created_at datetime NOT NULL,
            revoked tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (token_id)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($identities))) === $identities
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tokens))) === $tokens) {
            update_option('noticeboard_integration_schema', self::VERSION, true);
        }
    }

    public static function key(string $source, string $id): string
    {
        return hash('sha256', $source . "\0" . $id);
    }

    /** MySQL connection-owned lock: releases on disconnect, without stale lease takeover. */
    public static function lock(string $key): bool
    {
        global $wpdb;
        // Include database/site in the lock namespace; MySQL names are at most 64 bytes.
        $name = 'nb:' . substr(hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix . ':' . $key), 0, 60);
        return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $name)) === '1';
    }

    public static function unlock(string $key): void
    {
        global $wpdb;
        $name = 'nb:' . substr(hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix . ':' . $key), 0, 60);
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }

    public static function identity(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('identities') . ' WHERE identity_key = %s', $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function saveIdentity(string $key, string $source, string $id, int $postId, string $state): bool
    {
        global $wpdb;
        return false !== $wpdb->replace(self::table('identities'), [
            'identity_key' => $key, 'source' => $source, 'external_id' => $id,
            'post_id' => $postId, 'state' => $state,
        ], ['%s', '%s', '%s', '%d', '%s']);
    }
}
