<?php
/**
 * The protected-gallery configuration model: the JSON schema, its safe defaults,
 * the backend-editable global defaults, and the extension points the pro plugin
 * uses to add advanced layouts, overlay styles, and filter sources.
 *
 * Per-block attributes (stored as JSON in the block) override these defaults.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Gallery;

defined( 'ABSPATH' ) || exit;

final class GalleryConfig {

	public const OPTION = 'markguard_gallery_defaults';

	/**
	 * Base config, before the site's saved global defaults are layered on.
	 *
	 * @return array<string, mixed>
	 */
	public static function base_defaults(): array {
		$defaults = [
			'columns'    => 3,
			'gap'        => 12,
			'layout'     => 'grid',
			'radius'     => 8,
			'thumbMax'   => 500,
			'previewMax' => 1400,
			'presetId'   => 0,
			'policyId'   => 0,
			'accent'     => '#059669',
			'overlay'    => [
				'style'   => 'grid',
				'opacity' => 0.12,
				'color'   => '#ffffff',
			],
			'lightbox'   => [
				'showTitle'     => true,
				'showDownload'  => true,
				'downloadLabel' => __( 'Download original', 'rayetun-media-protection' ),
				'loginText'     => __( 'Log in to download the original file.', 'rayetun-media-protection' ),
			],
			'filters'    => [
				'enabled'  => false,
				'allLabel' => __( 'All', 'rayetun-media-protection' ),
			],
		];

		/**
		 * Filters the protected-gallery default config. The pro plugin uses this
		 * to introduce advanced options (extra layouts, overlay styles, etc.).
		 *
		 * @param array<string, mixed> $defaults
		 */
		return apply_filters( 'markguard_gallery_config_defaults', $defaults );
	}

	/**
	 * Base defaults merged with the site's saved global defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function global_defaults(): array {
		$saved = get_option( self::OPTION, [] );
		return self::merge( self::base_defaults(), is_array( $saved ) ? $saved : [] );
	}

	/** @param array<string, mixed> $config */
	public static function save_global_defaults( array $config ): void {
		update_option( self::OPTION, self::sanitize( $config ), false );
	}

	/**
	 * Available layouts. Free ships "grid"; the pro plugin appends masonry,
	 * justified, carousel, etc.
	 *
	 * @return array<int, array{slug:string, label:string}>
	 */
	public static function layouts(): array {
		$layouts = [
			[
				'slug'  => 'grid',
				'label' => __( 'Grid', 'rayetun-media-protection' ),
			],
		];

		/**
		 * Filters the list of gallery layouts. Pro adds advanced layouts here.
		 *
		 * @param array<int, array{slug:string, label:string}> $layouts
		 */
		return apply_filters( 'markguard_gallery_layouts', $layouts );
	}

	/**
	 * Available grid overlay styles (the subtle protective pattern on thumbs).
	 *
	 * @return string[]
	 */
	public static function overlay_styles(): array {
		/**
		 * Filters the allowed overlay styles.
		 *
		 * @param string[] $styles
		 */
		return apply_filters( 'markguard_gallery_overlay_styles', [ 'grid', 'diagonal', 'dots', 'none' ] );
	}

	/**
	 * Deep-merge overrides onto a base config (associative arrays only recurse).
	 *
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $over
	 * @return array<string, mixed>
	 */
	public static function merge( array $base, array $over ): array {
		foreach ( $over as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Whitelist + coerce a config array from an untrusted source (REST / block
	 * attributes) to known keys and safe ranges.
	 *
	 * @param array<string, mixed> $c
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $c ): array {
		$layout_slugs  = array_column( self::layouts(), 'slug' );
		$layout        = isset( $c['layout'] ) && in_array( (string) $c['layout'], $layout_slugs, true ) ? (string) $c['layout'] : 'grid';
		$overlay       = is_array( $c['overlay'] ?? null ) ? $c['overlay'] : [];
		$lightbox      = is_array( $c['lightbox'] ?? null ) ? $c['lightbox'] : [];
		$filters       = is_array( $c['filters'] ?? null ) ? $c['filters'] : [];
		$overlay_style = isset( $overlay['style'] ) && in_array( (string) $overlay['style'], self::overlay_styles(), true ) ? (string) $overlay['style'] : 'grid';

		$clean = [
			'columns'    => max( 1, min( 8, (int) ( $c['columns'] ?? 3 ) ) ),
			'gap'        => max( 0, min( 64, (int) ( $c['gap'] ?? 12 ) ) ),
			'layout'     => $layout,
			'radius'     => max( 0, min( 48, (int) ( $c['radius'] ?? 8 ) ) ),
			'thumbMax'   => max( 120, min( 1200, (int) ( $c['thumbMax'] ?? 500 ) ) ),
			'previewMax' => max( 600, min( 4000, (int) ( $c['previewMax'] ?? 1400 ) ) ),
			'presetId'   => max( 0, (int) ( $c['presetId'] ?? 0 ) ),
			// -1 = Everyone, 0 = Logged-in, >0 = named policy.
			'policyId'   => max( -1, (int) ( $c['policyId'] ?? 0 ) ),
			'accent'     => self::sanitize_color( (string) ( $c['accent'] ?? '#059669' ) ),
			'overlay'    => [
				'style'   => $overlay_style,
				'opacity' => max( 0.0, min( 1.0, (float) ( $overlay['opacity'] ?? 0.12 ) ) ),
				'color'   => self::sanitize_color( (string) ( $overlay['color'] ?? '#ffffff' ) ),
			],
			'lightbox'   => [
				'showTitle'     => ! empty( $lightbox['showTitle'] ),
				'showDownload'  => ! isset( $lightbox['showDownload'] ) || ! empty( $lightbox['showDownload'] ),
				'downloadLabel' => sanitize_text_field( (string) ( $lightbox['downloadLabel'] ?? __( 'Download original', 'rayetun-media-protection' ) ) ),
				'loginText'     => sanitize_text_field( (string) ( $lightbox['loginText'] ?? __( 'Log in to download the original file.', 'rayetun-media-protection' ) ) ),
			],
			'filters'    => [
				'enabled'  => ! empty( $filters['enabled'] ),
				'allLabel' => sanitize_text_field( (string) ( $filters['allLabel'] ?? __( 'All', 'rayetun-media-protection' ) ) ),
			],
		];

		/**
		 * Filters the sanitized gallery config, letting pro persist its own keys.
		 *
		 * @param array<string, mixed> $clean The sanitized config.
		 * @param array<string, mixed> $c     The raw input.
		 */
		return apply_filters( 'markguard_gallery_sanitize_config', $clean, $c );
	}

	private static function sanitize_color( string $color ): string {
		$hex = sanitize_hex_color( $color );
		return is_string( $hex ) && '' !== $hex ? $hex : '#ffffff';
	}
}
