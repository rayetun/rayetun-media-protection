<?php
/**
 * Detect Imagick availability. Cached per-request so callers can query freely.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class ImagickDetector {

	private static ?bool $available = null;

	public static function is_available(): bool {
		if ( null === self::$available ) {
			self::$available = extension_loaded( 'imagick' ) && class_exists( '\\Imagick' );
		}
		return self::$available;
	}

	/**
	 * Return the list of formats Imagick reports it can encode/decode, or [] if not available.
	 *
	 * @return string[]
	 */
	public static function supported_formats(): array {
		if ( ! self::is_available() ) {
			return [];
		}
		try {
			return \Imagick::queryFormats();
		} catch ( \Throwable $e ) {
			return [];
		}
	}
}
