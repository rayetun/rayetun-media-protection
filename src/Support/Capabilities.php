<?php
/**
 * Custom capability constants and role assignment.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const MANAGE  = 'manage_markguard';
	public const ANALYTICS = 'view_markguard_analytics';

	private const VERSION       = '1';
	private const VERSION_OPTION = 'markguard_caps_version';

	/**
	 * Grant RayEtun Media Protection capabilities to the administrator role. Idempotent and
	 * version-guarded so it also runs once on an already-active install.
	 */
	public static function ensure(): void {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '0' ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->add_cap( self::MANAGE );
			$admin->add_cap( self::ANALYTICS );
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Remove capabilities from every role (used on uninstall).
	 */
	public static function remove(): void {
		foreach ( wp_roles()->roles as $slug => $details ) {
			$role = get_role( $slug );
			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::MANAGE );
				$role->remove_cap( self::ANALYTICS );
			}
		}
		delete_option( self::VERSION_OPTION );
	}
}
