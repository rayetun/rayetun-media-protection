<?php
/**
 * Prunes access-log rows older than the configured retention window.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Database;

defined( 'ABSPATH' ) || exit;

final class LogPruner {

	public const OPTION_DAYS   = 'markguard_log_retention_days';
	private const DEFAULT_DAYS = 90;

	public static function retention_days(): int {
		$days = (int) get_option( self::OPTION_DAYS, self::DEFAULT_DAYS );
		return $days > 0 ? $days : self::DEFAULT_DAYS;
	}

	public static function prune(): void {
		global $wpdb;
		$table  = Schema::log_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::retention_days() * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff ) );
	}
}
