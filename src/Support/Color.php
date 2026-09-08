<?php
/**
 * Small color helpers shared by the watermark engines.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class Color {

	/**
	 * Convert a hex color (#RGB or #RRGGBB) to an [r, g, b] triple.
	 * Falls back to white on malformed input.
	 *
	 * @return array{0:int,1:int,2:int}
	 */
	public static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || 0 === preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return [ 255, 255, 255 ];
		}
		return [
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}
}
