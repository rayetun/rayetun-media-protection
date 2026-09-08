<?php
/**
 * Immutable value object describing one watermark application.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

final class WatermarkRule {

	/**
	 * @param 'text'|'image' $type              Watermark type.
	 * @param string         $text              Text to render (when type is 'text'). May contain tokens.
	 * @param string|null    $overlay_path      Absolute path to an image file (when type is 'image').
	 * @param Position       $position          Anchor position on the canvas.
	 * @param int            $offset_x          Positive integer; direction depends on position.
	 * @param int            $offset_y          Positive integer; direction depends on position.
	 * @param 'px'|'percent' $offset_unit       Unit for offsets.
	 * @param int            $opacity           0-100.
	 * @param int            $rotation          Degrees, 0-359.
	 * @param int            $min_source_width  Skip when source width is below this.
	 * @param int            $min_source_height Skip when source height is below this.
	 * @param int            $font_size         Text-only: font size in points.
	 * @param string         $font_color        Text-only: hex color (#RRGGBB).
	 * @param string|null    $font_path         Text-only: absolute path to a TTF; null uses default.
	 * @param float          $image_scale       Image-only: 0.0-1.0; overlay scaled to this fraction of source width.
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $text = '',
		public readonly ?string $overlay_path = null,
		public readonly Position $position = Position::BOTTOM_RIGHT,
		public readonly int $offset_x = 20,
		public readonly int $offset_y = 20,
		public readonly string $offset_unit = 'px',
		public readonly int $opacity = 60,
		public readonly int $rotation = 0,
		public readonly int $min_source_width = 200,
		public readonly int $min_source_height = 200,
		public readonly int $font_size = 24,
		public readonly string $font_color = '#FFFFFF',
		public readonly ?string $font_path = null,
		public readonly float $image_scale = 0.2
	) {}

	/** Convenience for callers who prefer array configs (e.g., CLI, REST). */
	public static function from_array( array $args ): self {
		$type = $args['type'] ?? 'text';
		if ( 'text' !== $type && 'image' !== $type ) {
			throw new \InvalidArgumentException( 'WatermarkRule type must be "text" or "image".' );
		}

		$position = $args['position'] ?? Position::BOTTOM_RIGHT;
		if ( is_string( $position ) ) {
			$position = Position::from( $position );
		}
		if ( ! $position instanceof Position ) {
			throw new \InvalidArgumentException( 'WatermarkRule position must be a Position enum or string.' );
		}

		return new self(
			type: $type,
			text: (string) ( $args['text'] ?? '' ),
			overlay_path: isset( $args['overlay_path'] ) ? (string) $args['overlay_path'] : null,
			position: $position,
			offset_x: (int) ( $args['offset_x'] ?? 20 ),
			offset_y: (int) ( $args['offset_y'] ?? 20 ),
			offset_unit: 'percent' === ( $args['offset_unit'] ?? 'px' ) ? 'percent' : 'px',
			opacity: max( 0, min( 100, (int) ( $args['opacity'] ?? 60 ) ) ),
			rotation: ( (int) ( $args['rotation'] ?? 0 ) ) % 360,
			min_source_width: max( 0, (int) ( $args['min_source_width'] ?? 200 ) ),
			min_source_height: max( 0, (int) ( $args['min_source_height'] ?? 200 ) ),
			font_size: max( 6, (int) ( $args['font_size'] ?? 24 ) ),
			font_color: (string) ( $args['font_color'] ?? '#FFFFFF' ),
			font_path: isset( $args['font_path'] ) ? (string) $args['font_path'] : null,
			image_scale: max( 0.01, min( 1.0, (float) ( $args['image_scale'] ?? 0.2 ) ) ),
		);
	}
}
