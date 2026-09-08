<?php
/**
 * File-type helpers for the download/library blocks: a coarse category from a
 * MIME type, a matching inline SVG icon (protected files never expose a real
 * thumbnail), and a short human label.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class FileType {

	public static function category( string $mime ): string {
		if ( 'application/pdf' === $mime ) {
			return 'pdf';
		}
		if ( str_starts_with( $mime, 'image/' ) ) {
			return 'image';
		}
		if ( str_starts_with( $mime, 'audio/' ) ) {
			return 'audio';
		}
		if ( str_starts_with( $mime, 'video/' ) ) {
			return 'video';
		}
		if ( in_array( $mime, [ 'application/zip', 'application/x-rar-compressed', 'application/vnd.rar', 'application/x-7z-compressed', 'application/gzip', 'application/x-tar' ], true ) ) {
			return 'archive';
		}
		foreach ( [ 'word', 'excel', 'spreadsheet', 'presentation', 'powerpoint', 'opendocument', 'text/', 'rtf' ] as $needle ) {
			if ( str_contains( $mime, $needle ) ) {
				return 'document';
			}
		}
		return 'generic';
	}

	public static function label( string $mime ): string {
		if ( 'application/pdf' === $mime ) {
			return 'PDF';
		}
		if ( str_starts_with( $mime, 'image/' ) ) {
			return strtoupper( str_replace( 'image/', '', $mime ) );
		}
		$parts = explode( '/', $mime );
		$tail  = strtoupper( (string) end( $parts ) );
		return preg_replace( '/[^A-Z0-9]/', '', $tail ) ?: 'FILE';
	}

	/**
	 * Inline SVG icon (24×24, currentColor) for the file's category.
	 */
	public static function icon_svg( string $mime ): string {
		$glyphs = [
			'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
			'pdf'      => '<path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M9 13h1.5a1.5 1.5 0 0 1 0 3H9zM9 13v5"/>',
			'archive'  => '<path d="M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4M10 15h4"/>',
			'audio'    => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
			'video'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M10 9l5 3-5 3z"/>',
			'document' => '<path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M8 13h8M8 17h8M8 9h2"/>',
			'generic'  => '<path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>',
		];
		$cat   = self::category( $mime );
		$glyph = $glyphs[ $cat ] ?? $glyphs['generic'];

		return '<span class="mg-download__icon" aria-hidden="true">'
			. '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
			. $glyph
			. '</svg></span>';
	}
}
