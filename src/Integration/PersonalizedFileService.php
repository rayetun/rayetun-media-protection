<?php
/**
 * Produces a personalized (watermarked) copy of a download for a specific
 * buyer, used by the e-commerce integrations.
 *
 * Given a source file and a Context (buyer email, order id, …), it copies the
 * file to a short-lived temp location, applies the configured e-commerce
 * watermark rule, and returns the temp path for the host plugin to serve. When
 * watermarking is disabled, the type is unsupported, or anything fails, it
 * returns the original path unchanged — a download is never broken by us.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration;

use Rayetun\MarkGuard\Storage\Backends\LocalBackend;
use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Registry as WatermarkRegistry;

defined( 'ABSPATH' ) || exit;

final class PersonalizedFileService {

	public function __construct(
		private readonly WatermarkRegistry $engines,
		private readonly WatermarkSettings $settings
	) {}

	/**
	 * Return a path to a watermarked copy of $source for $context, or the
	 * original $source when personalization does not apply.
	 */
	public function personalize( string $source, Context $context ): string {
		if ( ! $this->settings->is_enabled() ) {
			return $source;
		}

		$local = $this->resolve_local_path( $source );
		if ( null === $local || ! is_readable( $local ) ) {
			return $source; // Remote or unreadable — cannot watermark; serve as-is.
		}

		$mime   = $this->mime_of( $local );
		$engine = $this->engines->resolve_for_mime( $mime );
		if ( null === $engine ) {
			return $source; // Unsupported type (zip, mp4, …) — serve original.
		}

		$temp = $this->temp_copy( $local );
		if ( null === $temp ) {
			return $source;
		}

		$result = $engine->apply( $temp, $this->settings->rule(), $context );
		if ( ! $result->success ) {
			Filesystem::delete( $temp );
			return $source;
		}

		$this->schedule_cleanup( $temp );
		return $temp;
	}

	/**
	 * Resolve a WooCommerce/EDD/DLM file reference (which may be an absolute
	 * path, a site URL, or a content-relative URL) to a local absolute path.
	 * Returns null for genuinely remote files on other hosts.
	 */
	public function resolve_local_path( string $reference ): ?string {
		if ( '' === $reference ) {
			return null;
		}
		if ( is_readable( $reference ) && is_file( $reference ) ) {
			return $reference;
		}

		// Strip a leading scheme-relative or absolute URL down to a path.
		$url = $reference;
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
			$candidate = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
			return is_readable( $candidate ) ? $candidate : null;
		}

		$content_url = content_url();
		if ( 0 === strpos( $url, $content_url ) ) {
			$candidate = WP_CONTENT_DIR . substr( $url, strlen( $content_url ) );
			return is_readable( $candidate ) ? $candidate : null;
		}

		$home = home_url();
		if ( 0 === strpos( $url, $home ) ) {
			$candidate = untrailingslashit( ABSPATH ) . substr( $url, strlen( $home ) );
			return is_readable( $candidate ) ? $candidate : null;
		}

		return null;
	}

	private function mime_of( string $path ): string {
		$checked = wp_check_filetype( $path );
		if ( ! empty( $checked['type'] ) ) {
			return (string) $checked['type'];
		}
		$detected = function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : '';
		return $detected ?: 'application/octet-stream';
	}

	private function temp_copy( string $source ): ?string {
		$dir = self::temp_dir();
		$ext = pathinfo( $source, PATHINFO_EXTENSION );
		$dst = trailingslashit( $dir ) . 'mg-' . bin2hex( random_bytes( 16 ) ) . ( '' !== $ext ? '.' . $ext : '' );
		return Filesystem::copy( $source, $dst ) ? $dst : null;
	}

	/**
	 * Temp copies live under the protected root (so they are never publicly
	 * reachable by URL) in a dedicated tmp subdir swept by the GC cron.
	 */
	public static function temp_dir(): string {
		$dir = trailingslashit( LocalBackend::protected_root() ) . 'tmp';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	private function schedule_cleanup( string $temp ): void {
		// Delete after the response finishes. WordPress fires 'shutdown' even
		// when the host plugin exits after streaming. The daily GC cron mops up
		// anything a fatal error might leave behind.
		add_action(
			'shutdown',
			static function () use ( $temp ): void {
				Filesystem::delete( $temp );
			}
		);
	}

	/**
	 * Remove temp personalized copies older than two hours. Bound to a daily
	 * cron event.
	 */
	public static function gc(): void {
		$dir = self::temp_dir();
		$now = time();
		foreach ( (array) glob( trailingslashit( $dir ) . 'mg-*' ) as $file ) {
			if ( is_file( $file ) && ( $now - (int) filemtime( $file ) ) > 2 * HOUR_IN_SECONDS ) {
				Filesystem::delete( $file );
			}
		}
	}
}
