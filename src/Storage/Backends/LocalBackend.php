<?php
/**
 * Local filesystem storage backend.
 *
 * Files live under wp-content/uploads/markguard-protected/, which is guarded
 * by a generated .htaccess (Apache) and web.config (IIS) that deny direct HTTP
 * access. On nginx the directory is still only reachable through the signed
 * serve handler, since RayEtun Media Protection never emits a public URL to it; site owners
 * on nginx are additionally advised (in docs) to add a location deny block.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Storage\Backends;

use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Storage\Contracts\StorageBackend;

defined( 'ABSPATH' ) || exit;

final class LocalBackend implements StorageBackend {

	public function id(): string {
		return 'local';
	}

	public function label(): string {
		return __( 'Local filesystem', 'rayetun-media-protection' );
	}

	/**
	 * Absolute path to the protected root, ensuring it exists and is guarded.
	 */
	public static function protected_root(): string {
		$uploads = wp_upload_dir();
		$root    = trailingslashit( $uploads['basedir'] ) . 'markguard-protected';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
			self::write_guards( $root );
		}
		return $root;
	}

	private static function write_guards( string $root ): void {
		$htaccess = trailingslashit( $root ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "# RayEtun Media Protection — deny all direct access.\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
			self::put( $htaccess, $rules );
		}

		$webconfig = trailingslashit( $root ) . 'web.config';
		if ( ! file_exists( $webconfig ) ) {
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n\t<system.webServer>\n\t\t<authorization>\n"
				. "\t\t\t<deny users=\"*\" />\n"
				. "\t\t</authorization>\n\t</system.webServer>\n</configuration>\n";
			self::put( $webconfig, $xml );
		}

		$index = trailingslashit( $root ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			self::put( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	public function store( string $source_path, string $logical_key ): ?string {
		if ( ! is_readable( $source_path ) ) {
			return null;
		}
		$root   = self::protected_root();
		$shard  = substr( $logical_key, 0, 2 );
		$dir    = trailingslashit( $root ) . $shard;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$ext         = pathinfo( $source_path, PATHINFO_EXTENSION );
		$destination = trailingslashit( $dir ) . $logical_key . ( '' !== $ext ? '.' . $ext : '' );

		if ( ! Filesystem::move( $source_path, $destination ) ) {
			return null;
		}
		return $destination;
	}

	public function stream( string $storage_path, string $download_filename, string $mime_type ): void {
		if ( ! is_readable( $storage_path ) ) {
			status_header( 404 );
			return;
		}

		nocache_headers();
		header( 'Content-Type: ' . ( '' !== $mime_type ? $mime_type : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $download_filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $storage_path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( function_exists( 'wp_ob_end_flush_all' ) ) {
			wp_ob_end_flush_all();
		}

		// readfile streams internally in chunks; correct tool for serving a
		// potentially large protected download. No WP_Filesystem equivalent
		// streams to the client without buffering the whole file in memory.
		readfile( $storage_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}

	public function delete( string $storage_path ): bool {
		return Filesystem::delete( $storage_path );
	}

	public function exists( string $storage_path ): bool {
		return is_readable( $storage_path );
	}

	private static function put( string $path, string $contents ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}
		if ( ! empty( $wp_filesystem ) ) {
			$wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}
	}
}
