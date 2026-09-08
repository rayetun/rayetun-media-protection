<?php
/**
 * Site-wide defaults for the Protected File Library block, edited in the Blocks
 * hub. New blocks inherit these; each block can still override them.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

defined( 'ABSPATH' ) || exit;

final class LibraryConfig {

	public const OPTION = 'markguard_library_defaults';

	/** @return array<string, mixed> */
	public static function base(): array {
		return [
			'columns'  => 2,
			'gap'      => 12,
			'showInfo' => true,
			'accent'   => '#059669',
			'template' => 'card',
			'policyId' => 0,
			'labels'   => [
				'download' => __( 'Download', 'rayetun-media-protection' ),
				'locked'   => __( 'Log in to download', 'rayetun-media-protection' ),
			],
			'filters'  => [
				'enabled'  => false,
				'allLabel' => __( 'All', 'rayetun-media-protection' ),
			],
		];
	}

	/** @return array<string, mixed> */
	public static function defaults(): array {
		$saved = get_option( self::OPTION, [] );
		return is_array( $saved ) && ! empty( $saved ) ? self::sanitize( $saved ) : self::base();
	}

	/** @param array<string, mixed> $c */
	public static function save( array $c ): void {
		update_option( self::OPTION, self::sanitize( $c ), false );
	}

	/**
	 * @param array<string, mixed> $c
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $c ): array {
		$hex     = sanitize_hex_color( (string) ( $c['accent'] ?? '' ) );
		$labels  = is_array( $c['labels'] ?? null ) ? $c['labels'] : [];
		$filters = is_array( $c['filters'] ?? null ) ? $c['filters'] : [];
		return [
			'columns'  => max( 1, min( 6, (int) ( $c['columns'] ?? 2 ) ) ),
			'gap'      => max( 0, min( 48, (int) ( $c['gap'] ?? 12 ) ) ),
			'showInfo' => ! isset( $c['showInfo'] ) || ! empty( $c['showInfo'] ),
			'accent'   => is_string( $hex ) && '' !== $hex ? $hex : '#059669',
			'template' => ( ( $c['template'] ?? 'card' ) === 'tile' ) ? 'tile' : 'card',
			'policyId' => max( -1, (int) ( $c['policyId'] ?? 0 ) ),
			'labels'   => [
				'download' => sanitize_text_field( (string) ( $labels['download'] ?? __( 'Download', 'rayetun-media-protection' ) ) ),
				'locked'   => sanitize_text_field( (string) ( $labels['locked'] ?? __( 'Log in to download', 'rayetun-media-protection' ) ) ),
			],
			'filters'  => [
				'enabled'  => ! empty( $filters['enabled'] ),
				'allLabel' => sanitize_text_field( (string) ( $filters['allLabel'] ?? __( 'All', 'rayetun-media-protection' ) ) ),
			],
		];
	}
}
