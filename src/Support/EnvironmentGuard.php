<?php
/**
 * Environment guard — checks PHP and WordPress version requirements without
 * relying on the Composer autoloader.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class EnvironmentGuard {

	/**
	 * Whether the current environment meets both the PHP and WordPress minimums.
	 */
	public static function meets_requirements(): bool {
		return self::php_ok() && self::wp_ok();
	}

	/**
	 * Register an admin notice explaining why the plugin did not boot.
	 */
	public static function register_admin_notice(): void {
		add_action( 'admin_notices', [ self::class, 'render_notice' ] );
	}

	/**
	 * Render the admin notice.
	 */
	public static function render_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$messages = [];

		if ( ! self::php_ok() ) {
			$messages[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'RayEtun Media Protection requires PHP %1$s or newer. Your server is running PHP %2$s.', 'rayetun-media-protection' ),
				MARKGUARD_MIN_PHP,
				PHP_VERSION
			);
		}

		if ( ! self::wp_ok() ) {
			global $wp_version;
			$messages[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version */
				__( 'RayEtun Media Protection requires WordPress %1$s or newer. Your site is running WordPress %2$s.', 'rayetun-media-protection' ),
				MARKGUARD_MIN_WP,
				$wp_version
			);
		}

		if ( empty( $messages ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'RayEtun Media Protection could not activate:', 'rayetun-media-protection' ) . '</p><ul>';
		foreach ( $messages as $message ) {
			echo '<li>' . esc_html( $message ) . '</li>';
		}
		echo '</ul></div>';
	}

	private static function php_ok(): bool {
		return version_compare( PHP_VERSION, MARKGUARD_MIN_PHP, '>=' );
	}

	private static function wp_ok(): bool {
		global $wp_version;
		return version_compare( (string) $wp_version, MARKGUARD_MIN_WP, '>=' );
	}
}
