<?php
/**
 * Position enum for watermark anchoring — nine standard positions.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

enum Position: string {
	case TOP_LEFT      = 'top-left';
	case TOP_CENTER    = 'top-center';
	case TOP_RIGHT     = 'top-right';
	case CENTER_LEFT   = 'center-left';
	case CENTER        = 'center';
	case CENTER_RIGHT  = 'center-right';
	case BOTTOM_LEFT   = 'bottom-left';
	case BOTTOM_CENTER = 'bottom-center';
	case BOTTOM_RIGHT  = 'bottom-right';

	/**
	 * Compute the top-left pixel coordinate for a watermark of the given size,
	 * within a canvas of the given size, honoring pixel/percentage offsets.
	 *
	 * @return array{0:int,1:int} [x, y]
	 */
	public function compute_xy(
		int $canvas_width,
		int $canvas_height,
		int $watermark_width,
		int $watermark_height,
		int $offset_x,
		int $offset_y,
		string $offset_unit
	): array {
		$ox = 'percent' === $offset_unit ? (int) round( $canvas_width * $offset_x / 100 ) : $offset_x;
		$oy = 'percent' === $offset_unit ? (int) round( $canvas_height * $offset_y / 100 ) : $offset_y;

		[ $anchor_x, $anchor_y ] = match ( $this ) {
			self::TOP_LEFT      => [ 0, 0 ],
			self::TOP_CENTER    => [ (int) ( ( $canvas_width - $watermark_width ) / 2 ), 0 ],
			self::TOP_RIGHT     => [ $canvas_width - $watermark_width, 0 ],
			self::CENTER_LEFT   => [ 0, (int) ( ( $canvas_height - $watermark_height ) / 2 ) ],
			self::CENTER        => [
				(int) ( ( $canvas_width - $watermark_width ) / 2 ),
				(int) ( ( $canvas_height - $watermark_height ) / 2 ),
			],
			self::CENTER_RIGHT  => [
				$canvas_width - $watermark_width,
				(int) ( ( $canvas_height - $watermark_height ) / 2 ),
			],
			self::BOTTOM_LEFT   => [ 0, $canvas_height - $watermark_height ],
			self::BOTTOM_CENTER => [
				(int) ( ( $canvas_width - $watermark_width ) / 2 ),
				$canvas_height - $watermark_height,
			],
			self::BOTTOM_RIGHT  => [ $canvas_width - $watermark_width, $canvas_height - $watermark_height ],
		};

		// Positive offset moves toward center from the edge; makes UI intuitive.
		[ $sign_x, $sign_y ] = match ( $this ) {
			self::TOP_LEFT      => [ 1, 1 ],
			self::TOP_CENTER    => [ 0, 1 ],
			self::TOP_RIGHT     => [ -1, 1 ],
			self::CENTER_LEFT   => [ 1, 0 ],
			self::CENTER        => [ 0, 0 ],
			self::CENTER_RIGHT  => [ -1, 0 ],
			self::BOTTOM_LEFT   => [ 1, -1 ],
			self::BOTTOM_CENTER => [ 0, -1 ],
			self::BOTTOM_RIGHT  => [ -1, -1 ],
		};

		return [ $anchor_x + ( $sign_x * $ox ), $anchor_y + ( $sign_y * $oy ) ];
	}
}
