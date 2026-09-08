<?php
/**
 * Site-wide defaults for the Protected Download block, edited in the Blocks hub.
 * New blocks inherit these; each block can still override them.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

defined( 'ABSPATH' ) || exit;

final class DownloadConfig {

	public const OPTION = 'markguard_download_defaults';

	/** @return array<string, mixed> */
	public static function base(): array {
		return [
			'accent'     => '#059669',
			'template'   => 'card',
			'showInfo'   => true,
			'policyId'   => 0,
			'label'      => __( 'Download', 'rayetun-media-protection' ),
			'lockedText' => __( 'Log in to download', 'rayetun-media-protection' ),
		];
	}

	/** @return array<string, mixed> */
	public static function defaults(): array {
		$saved = get_option( self::OPTION, [] );
		return array_merge( self::base(), is_array( $saved ) ? self::sanitize( $saved ) : [] );
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
		$hex = sanitize_hex_color( (string) ( $c['accent'] ?? '' ) );
		return [
			'accent'     => is_string( $hex ) && '' !== $hex ? $hex : '#059669',
			'template'   => ( ( $c['template'] ?? 'card' ) === 'tile' ) ? 'tile' : 'card',
			'showInfo'   => ! isset( $c['showInfo'] ) || ! empty( $c['showInfo'] ),
			'policyId'   => max( -1, (int) ( $c['policyId'] ?? 0 ) ),
			'label'      => sanitize_text_field( (string) ( $c['label'] ?? __( 'Download', 'rayetun-media-protection' ) ) ),
			'lockedText' => sanitize_text_field( (string) ( $c['lockedText'] ?? __( 'Log in to download', 'rayetun-media-protection' ) ) ),
		];
	}
}
