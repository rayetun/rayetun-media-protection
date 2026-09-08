<?php
/**
 * Database schema — creates and drops RayEtun Media Protection's custom tables.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const DB_VERSION = '4';

	public static function files_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'markguard_files';
	}

	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'markguard_access_log';
	}

	public static function presets_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'markguard_watermark_presets';
	}

	public static function policies_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'markguard_access_policies';
	}

	/**
	 * Create or update tables via dbDelta. Idempotent.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$files           = self::files_table();
		$log             = self::log_table();

		// dbDelta is whitespace- and format-sensitive; keep two spaces after PRIMARY KEY.
		$files_sql = "CREATE TABLE {$files} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			logical_key varchar(191) NOT NULL,
			storage_path text NOT NULL,
			original_filename varchar(255) NOT NULL DEFAULT '',
			backend varchar(64) NOT NULL DEFAULT 'local',
			mime_type varchar(191) NOT NULL DEFAULT '',
			size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
			rule_set_id bigint(20) unsigned NOT NULL DEFAULT 0,
			policy_json longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY logical_key (logical_key),
			KEY attachment_id (attachment_id),
			KEY backend (backend),
			KEY rule_set_id (rule_set_id)
		) {$charset_collate};";

		$log_sql = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			file_id bigint(20) unsigned NOT NULL DEFAULT 0,
			link_nonce varchar(64) NOT NULL DEFAULT '',
			event varchar(32) NOT NULL DEFAULT '',
			reason varchar(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_hash char(64) NOT NULL DEFAULT '',
			ua_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY file_id (file_id),
			KEY link_nonce (link_nonce),
			KEY event (event),
			KEY created_at (created_at)
		) {$charset_collate};";

		$presets = self::presets_table();
		$presets_sql = "CREATE TABLE {$presets} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			config_json longtext NOT NULL,
			is_default_upload tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY is_default_upload (is_default_upload)
		) {$charset_collate};";

		$policies = self::policies_table();
		$policies_sql = "CREATE TABLE {$policies} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			policy_json longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id)
		) {$charset_collate};";

		dbDelta( $files_sql );
		dbDelta( $log_sql );
		dbDelta( $presets_sql );
		dbDelta( $policies_sql );

		update_option( 'markguard_db_version', self::DB_VERSION, false );
	}

	/**
	 * Drop all RayEtun Media Protection tables. Called from uninstall only.
	 */
	public static function drop(): void {
		global $wpdb;
		$files    = self::files_table();
		$log      = self::log_table();
		$presets  = self::presets_table();
		$policies = self::policies_table();
		// Table identifiers cannot be bound as prepared parameters; these names
		// are built from the trusted $wpdb->prefix and hard-coded suffixes.
		$wpdb->query( "DROP TABLE IF EXISTS `{$log}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "DROP TABLE IF EXISTS `{$files}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "DROP TABLE IF EXISTS `{$presets}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "DROP TABLE IF EXISTS `{$policies}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		delete_option( 'markguard_db_version' );
	}
}
