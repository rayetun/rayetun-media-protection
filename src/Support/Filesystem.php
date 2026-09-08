<?php
/**
 * Thin wrapper around WP_Filesystem for the file operations RayEtun Media Protection needs.
 *
 * WordPress.org Plugin Check flags direct `rename()`, `unlink()`,
 * `file_get_contents()`, and friends. Using WP_Filesystem is the reviewer-
 * accepted alternative and works transparently for direct, FTP, and SSH
 * setups.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class Filesystem {

	private static ?bool $wp_ready = null;

	/**
	 * Lazy-initialize the global WP_Filesystem. Safe to call repeatedly.
	 * Returns false (never fatals) when WordPress isn't loaded — unit tests
	 * exercise the engine without a full WP bootstrap.
	 */
	private static function init(): bool {
		if ( null !== self::$wp_ready ) {
			return self::$wp_ready;
		}

		$file_helpers = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/file.php' : null;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			if ( null === $file_helpers || ! is_readable( $file_helpers ) ) {
				self::$wp_ready = false;
				return false;
			}
			require_once $file_helpers;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}
		self::$wp_ready = ! empty( $wp_filesystem );
		return self::$wp_ready;
	}

	/**
	 * Move / rename a file with overwrite semantics.
	 * Uses WP_Filesystem when WordPress is loaded (Plugin Check compliance),
	 * falls back to native rename+copy when running outside WordPress (tests).
	 */
	public static function move( string $source, string $destination ): bool {
		if ( self::init() ) {
			global $wp_filesystem;
			if ( $wp_filesystem->move( $source, $destination, true ) ) {
				return true;
			}
			if ( $wp_filesystem->copy( $source, $destination, true ) ) {
				self::delete( $source );
				return true;
			}
			return false;
		}
		// Non-WP context: only reachable from PHPUnit unit tests that exercise
		// the engine directly without a WordPress bootstrap. Under WordPress
		// (activation, admin request, cron, CLI-with-WP), self::init() succeeds
		// and this branch never runs.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Unit-test-only fallback; WP_Filesystem preferred branch above.
		if ( @rename( $source, $destination ) ) {
			return true;
		}
		if ( @copy( $source, $destination ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit-test-only fallback; wp_delete_file preferred branch below.
			@unlink( $source );
			return true;
		}
		return false;
	}

	/**
	 * Copy a file, overwriting the destination. Uses WP_Filesystem under
	 * WordPress; falls back to native copy() outside it (unit tests).
	 */
	public static function copy( string $source, string $destination ): bool {
		if ( self::init() ) {
			global $wp_filesystem;
			return (bool) $wp_filesystem->copy( $source, $destination, true );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Unit-test-only fallback; WP_Filesystem preferred branch above.
		return @copy( $source, $destination );
	}

	/**
	 * Read a file's contents. Returns null on failure.
	 */
	public static function read( string $path ): ?string {
		if ( self::init() ) {
			global $wp_filesystem;
			$contents = $wp_filesystem->get_contents( $path );
			return false === $contents ? null : $contents;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Unit-test-only fallback; WP_Filesystem preferred branch above.
		$contents = @file_get_contents( $path );
		return false === $contents ? null : $contents;
	}

	/**
	 * Write contents to a file, creating or overwriting it. Uses WP_Filesystem
	 * under WordPress; falls back to native file_put_contents outside it (tests).
	 */
	public static function put_contents( string $path, string $contents ): bool {
		if ( self::init() ) {
			global $wp_filesystem;
			return (bool) $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- Unit-test-only fallback; WP_Filesystem preferred branch above.
		return false !== @file_put_contents( $path, $contents );
	}

	/**
	 * Delete a file; no-op if it does not exist.
	 */
	public static function delete( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}
		if ( self::init() && function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit-test-only fallback; wp_delete_file used above under WP.
			@unlink( $path );
		}
		return ! file_exists( $path );
	}
}
