<?php
/**
 * RayEtun Media Protection uninstaller — runs from uninstall.php when the plugin is deleted.
 *
 * Removes: custom tables, plugin options, and the protected-uploads directory.
 * Attachment posts and user media are never touched.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard;

use Rayetun\MarkGuard\Database\Schema;
use Rayetun\MarkGuard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Uninstaller {

	public static function run(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}

		Schema::drop();

		delete_option( 'markguard_signing_salt' );
		delete_option( 'markguard_db_version' );
		delete_option( 'markguard_ecommerce_watermark' );
		delete_option( 'markguard_auto_watermark' );
		delete_option( 'markguard_auto_protect' );
		delete_option( 'markguard_deterrents' );
		delete_option( 'markguard_clean_downloads' );
		delete_option( 'markguard_gallery_defaults' );
		delete_option( 'markguard_download_defaults' );
		delete_option( 'markguard_library_defaults' );
		delete_option( 'markguard_log_retention_days' );

		delete_metadata( 'user', 0, 'markguard_onboarding_dismissed', '', true );
		delete_metadata( 'post', 0, '_markguard_clean_file_id', '', true );

		Capabilities::remove();

		wp_clear_scheduled_hook( 'markguard_gc_temp_files' );

		self::remove_upload_dir( 'markguard-protected' );
		self::remove_upload_dir( 'markguard-previews' );
	}

	private static function remove_upload_dir( string $name ): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}
		$root = trailingslashit( $uploads['basedir'] ) . $name;
		if ( ! is_dir( $root ) ) {
			return;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}
		if ( ! empty( $wp_filesystem ) ) {
			$wp_filesystem->delete( $root, true );
		}
	}
}
