<?php
/**
 * Provides the HMAC signing secret for download tokens.
 *
 * Combines WordPress's own auth salt with a RayEtun Media Protection-specific option so that
 * (a) tokens can be invalidated site-wide by rotating the option, and (b) the
 * secret is unique per install.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class Signing {

	private const OPTION = 'markguard_signing_salt';

	public static function secret(): string {
		$salt = get_option( self::OPTION );
		if ( ! is_string( $salt ) || '' === $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( self::OPTION, $salt, false );
		}
		return wp_salt( 'auth' ) . $salt;
	}

	/**
	 * Rotate the signing salt, invalidating every existing download link.
	 */
	public static function rotate(): void {
		update_option( self::OPTION, wp_generate_password( 64, true, true ), false );
	}
}
