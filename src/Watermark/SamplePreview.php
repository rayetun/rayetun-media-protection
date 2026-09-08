<?php
/**
 * Renders a true watermark preview by applying a rule to a generated sample
 * image and returning it as a data URI. Used by the editor's "Render real
 * preview" button so authors see exact engine output (fonts, blending,
 * rotation) without touching their own files.
 *
 * The sample is always an image — a watermark's placement, opacity, rotation,
 * colour, and resolved tokens look identical whether the eventual target is an
 * image or a PDF, so one representative preview covers both.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Watermark\Registry as WatermarkRegistry;

defined( 'ABSPATH' ) || exit;

final class SamplePreview {

	private const WIDTH  = 800;
	private const HEIGHT = 500;

	public function __construct(
		private readonly WatermarkRegistry $engines
	) {}

	/**
	 * @return string|null Data URI (image/png) or null on failure.
	 */
	public function render( WatermarkRule $rule ): ?string {
		if ( ! extension_loaded( 'gd' ) ) {
			return null;
		}

		$sample = $this->make_sample();
		if ( null === $sample ) {
			return null;
		}

		$engine = $this->engines->resolve_for_mime( 'image/png' );
		if ( null === $engine ) {
			Filesystem::delete( $sample );
			return null;
		}

		// A rule with a large min-size would skip our sample; neutralise it.
		$preview_rule = $this->with_no_threshold( $rule );
		$context      = new Context(
			user_id: 42,
			user_email: 'buyer@example.com',
			request_ip: '203.0.113.7',
			order_id: 1042
		);

		$result = $engine->apply( $sample, $preview_rule, $context );
		if ( ! $result->success ) {
			Filesystem::delete( $sample );
			return null;
		}

		$bytes = Filesystem::read( $result->output_path );
		Filesystem::delete( $result->output_path );
		if ( null === $bytes ) {
			return null;
		}

		return 'data:image/png;base64,' . base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building a data: URI for the inline watermark preview image.
	}

	private function with_no_threshold( WatermarkRule $rule ): WatermarkRule {
		return new WatermarkRule(
			type: $rule->type,
			text: $rule->text,
			overlay_path: $rule->overlay_path,
			position: $rule->position,
			offset_x: $rule->offset_x,
			offset_y: $rule->offset_y,
			offset_unit: $rule->offset_unit,
			opacity: $rule->opacity,
			rotation: $rule->rotation,
			min_source_width: 0,
			min_source_height: 0,
			font_size: $rule->font_size,
			font_color: $rule->font_color,
			font_path: $rule->font_path,
			image_scale: $rule->image_scale
		);
	}

	/**
	 * Generate a neutral sample photo (soft diagonal gradient + subtle grid) so
	 * the watermark is legible against both light and dark areas.
	 *
	 * @return string|null Path to the sample PNG.
	 */
	private function make_sample(): ?string {
		$img = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( false === $img ) {
			return null;
		}

		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$t = $y / self::HEIGHT;
			$r = (int) round( 90 + ( 70 * $t ) );
			$g = (int) round( 110 + ( 60 * $t ) );
			$b = (int) round( 140 + ( 40 * $t ) );
			$color = imagecolorallocate( $img, $r, $g, $b );
			imageline( $img, 0, $y, self::WIDTH, $y, $color );
		}

		$grid = imagecolorallocatealpha( $img, 255, 255, 255, 110 );
		for ( $x = 0; $x < self::WIDTH; $x += 50 ) {
			imageline( $img, $x, 0, $x, self::HEIGHT, $grid );
		}
		for ( $y = 0; $y < self::HEIGHT; $y += 50 ) {
			imageline( $img, 0, $y, self::WIDTH, $y, $grid );
		}

		$dir  = get_temp_dir();
		$path = trailingslashit( $dir ) . 'mg-sample-' . bin2hex( random_bytes( 6 ) ) . '.png';
		$saved = imagepng( $img, $path );
		imagedestroy( $img );

		return $saved ? $path : null;
	}
}
