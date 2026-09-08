<?php
/**
 * Runs schema installation/upgrades when the stored DB version is behind.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Database;

defined( 'ABSPATH' ) || exit;

final class Upgrader {

	public static function maybe_upgrade(): void {
		$installed = (string) get_option( 'markguard_db_version', '0' );
		if ( version_compare( $installed, Schema::DB_VERSION, '>=' ) ) {
			return;
		}
		Schema::install();
	}
}
