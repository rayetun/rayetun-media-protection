<?php
/**
 * A named, reusable watermark preset. Stores the editor's camelCase config dict
 * verbatim (so fields like the overlay attachment id round-trip) and derives a
 * WatermarkRule from it on demand.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

final class Preset {

	/** @param array<string, mixed> $config */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly array $config,
		public readonly bool $is_default_upload
	) {}

	public function rule(): WatermarkRule {
		return self::rule_from_config( $this->config );
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'id'              => $this->id,
			'name'            => $this->name,
			'isDefaultUpload' => $this->is_default_upload,
			'rule'            => self::normalize( $this->config ),
		];
	}

	/**
	 * Whitelist + coerce the config dict to known keys with safe defaults.
	 *
	 * @param array<string, mixed> $c
	 * @return array<string, mixed>
	 */
	public static function normalize( array $c ): array {
		$type = ( isset( $c['type'] ) && 'image' === $c['type'] ) ? 'image' : 'text';
		return [
			'type'        => $type,
			'text'        => (string) ( $c['text'] ?? '' ),
			'overlayId'   => (int) ( $c['overlayId'] ?? 0 ),
			'overlayPath' => esc_url_raw( (string) ( $c['overlayPath'] ?? '' ) ),
			'position'    => (string) ( $c['position'] ?? 'bottom-right' ),
			'offsetX'     => (int) ( $c['offsetX'] ?? 20 ),
			'offsetY'     => (int) ( $c['offsetY'] ?? 20 ),
			'offsetUnit'  => ( isset( $c['offsetUnit'] ) && 'percent' === $c['offsetUnit'] ) ? 'percent' : 'px',
			'opacity'     => max( 0, min( 100, (int) ( $c['opacity'] ?? 60 ) ) ),
			'rotation'    => ( (int) ( $c['rotation'] ?? 0 ) ) % 360,
			'fontSize'    => max( 6, (int) ( $c['fontSize'] ?? 24 ) ),
			'fontColor'   => (string) ( $c['fontColor'] ?? '#FFFFFF' ),
			'imageScale'  => max( 0.01, min( 1.0, (float) ( $c['imageScale'] ?? 0.2 ) ) ),
		];
	}

	/**
	 * Build a WatermarkRule, resolving an overlay attachment id to a real file
	 * path so the engine can read it.
	 *
	 * @param array<string, mixed> $config
	 */
	public static function rule_from_config( array $config ): WatermarkRule {
		$c            = self::normalize( $config );
		$overlay_path = null;

		if ( $c['overlayId'] > 0 && function_exists( 'get_attached_file' ) ) {
			$file = get_attached_file( $c['overlayId'] );
			if ( is_string( $file ) && is_readable( $file ) ) {
				$overlay_path = $file;
			}
		}

		return WatermarkRule::from_array(
			[
				'type'         => $c['type'],
				'text'         => $c['text'],
				'overlay_path' => $overlay_path,
				'position'     => $c['position'],
				'offset_x'     => $c['offsetX'],
				'offset_y'     => $c['offsetY'],
				'offset_unit'  => $c['offsetUnit'],
				'opacity'      => $c['opacity'],
				'rotation'     => $c['rotation'],
				'font_size'    => $c['fontSize'],
				'font_color'   => $c['fontColor'],
				'image_scale'  => $c['imageScale'],
			]
		);
	}
}
