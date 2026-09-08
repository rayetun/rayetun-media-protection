<?php
/**
 * Builds an AccessRequest from the current WordPress/HTTP request.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

final class RequestFactory {

	public static function from_globals( int $clicks_used = 0 ): AccessRequest {
		$user  = wp_get_current_user();
		$roles = ( $user instanceof \WP_User ) ? array_values( $user->roles ) : [];

		return new AccessRequest(
			user_id: get_current_user_id(),
			user_roles: $roles,
			ip: self::client_ip(),
			referrer_host: self::referrer_host(),
			clicks_used: $clicks_used
		);
	}

	private static function client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	}

	private static function referrer_host(): string {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}
		$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$host    = wp_parse_url( $referer, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	public static function user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}
}
