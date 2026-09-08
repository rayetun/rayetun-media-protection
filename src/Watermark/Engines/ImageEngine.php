<?php
/**
 * Image watermark engine — text or image overlay on JPEG, PNG, and WebP.
 *
 * Uses Imagick when available for quality (subpixel text rendering, native
 * blending, EXIF preservation). Falls back to GD when Imagick is not loaded.
 *
 * Non-destructive by contract: writes to a sibling temp file, then atomically
 * renames over the source. Callers wanting to keep an original should copy
 * before calling apply().
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark\Engines;

use Rayetun\MarkGuard\Support\Color;
use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Support\ImagickDetector;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Contracts\WatermarkEngine;
use Rayetun\MarkGuard\Watermark\Result;
use Rayetun\MarkGuard\Watermark\TokenRegistry;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

defined( 'ABSPATH' ) || exit;

final class ImageEngine implements WatermarkEngine {

	private const SUPPORTED_MIME_TYPES = [ 'image/jpeg', 'image/png', 'image/webp' ];

	public function __construct(
		private readonly ?TokenRegistry $tokens = null
	) {}

	public function id(): string {
		return 'image';
	}

	public function label(): string {
		return __( 'Image (JPEG, PNG, WebP)', 'rayetun-media-protection' );
	}

	public function supported_mime_types(): array {
		return self::SUPPORTED_MIME_TYPES;
	}

	public function is_available(): bool {
		// Either Imagick or GD is sufficient.
		return ImagickDetector::is_available() || extension_loaded( 'gd' );
	}

	public function apply( string $source_path, WatermarkRule $rule, Context $context ): Result {
		if ( ! is_readable( $source_path ) ) {
			return Result::failure( $source_path, sprintf( 'Source not readable: %s', $source_path ) );
		}

		$dimensions = @getimagesize( $source_path );
		if ( false === $dimensions ) {
			return Result::failure( $source_path, 'getimagesize() failed — source may not be a valid image.' );
		}
		[ $width, $height, $image_type ] = $dimensions;

		if ( $width < $rule->min_source_width || $height < $rule->min_source_height ) {
			return Result::skipped(
				$source_path,
				sprintf( 'Source %dx%d below threshold %dx%d', $width, $height, $rule->min_source_width, $rule->min_source_height )
			);
		}

		$mime_type = image_type_to_mime_type( $image_type );
		if ( ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) ) {
			return Result::failure( $source_path, sprintf( 'Unsupported MIME type: %s', $mime_type ) );
		}

		// Resolve any dynamic tokens in text watermarks up front.
		$text = 'text' === $rule->type
			? ( $this->tokens?->resolve( $rule->text, $context ) ?? $rule->text )
			: $rule->text;

		if ( ImagickDetector::is_available() ) {
			return $this->apply_with_imagick( $source_path, $rule, $text );
		}
		if ( extension_loaded( 'gd' ) ) {
			return $this->apply_with_gd( $source_path, $rule, $image_type, $text );
		}
		return Result::failure( $source_path, 'Neither Imagick nor GD is available.' );
	}

	// -----------------------------------------------------------------
	// Imagick path — preferred; preserves EXIF, subpixel text rendering.
	// -----------------------------------------------------------------
	private function apply_with_imagick( string $source_path, WatermarkRule $rule, string $text ): Result {
		try {
			$canvas = new \Imagick( $source_path );
			$canvas_width  = $canvas->getImageWidth();
			$canvas_height = $canvas->getImageHeight();

			$overlay = 'text' === $rule->type
				? $this->build_text_overlay_imagick( $rule, $text )
				: $this->build_image_overlay_imagick( $rule, $canvas_width );

			if ( null === $overlay ) {
				$canvas->clear();
				return Result::failure( $source_path, 'Failed to build watermark overlay.' );
			}

			if ( 0 !== $rule->rotation ) {
				$overlay->rotateImage( new \ImagickPixel( 'transparent' ), $rule->rotation );
			}

			$overlay_width  = $overlay->getImageWidth();
			$overlay_height = $overlay->getImageHeight();

			[ $x, $y ] = $rule->position->compute_xy(
				$canvas_width,
				$canvas_height,
				$overlay_width,
				$overlay_height,
				$rule->offset_x,
				$rule->offset_y,
				$rule->offset_unit
			);

			$overlay->evaluateImage( \Imagick::EVALUATE_MULTIPLY, $rule->opacity / 100, \Imagick::CHANNEL_ALPHA );
			$canvas->compositeImage( $overlay, \Imagick::COMPOSITE_OVER, $x, $y );

			$tmp_path = $this->tmp_sibling( $source_path );
			$canvas->writeImage( $tmp_path );
			$overlay->clear();
			$canvas->clear();

			if ( ! Filesystem::move( $tmp_path, $source_path ) ) {
				Filesystem::delete( $tmp_path );
				return Result::failure( $source_path, 'Failed to replace source with watermarked output.' );
			}

			return Result::success( $source_path, 'imagick' );
		} catch ( \Throwable $e ) {
			return Result::failure( $source_path, 'Imagick error: ' . $e->getMessage() );
		}
	}

	private function build_text_overlay_imagick( WatermarkRule $rule, string $text ): ?\Imagick {
		try {
			$draw = new \ImagickDraw();
			if ( null !== $rule->font_path && is_readable( $rule->font_path ) ) {
				$draw->setFont( $rule->font_path );
			}
			$draw->setFontSize( $rule->font_size );
			$draw->setFillColor( new \ImagickPixel( $rule->font_color ) );
			$draw->setTextAntialias( true );

			$metrics = ( new \Imagick() )->queryFontMetrics( $draw, $text );

			$overlay = new \Imagick();
			$overlay->newImage(
				(int) ceil( $metrics['textWidth'] ) + 4,
				(int) ceil( $metrics['textHeight'] ) + 4,
				new \ImagickPixel( 'transparent' )
			);
			$overlay->setImageFormat( 'png' );
			$overlay->annotateImage( $draw, 2, (int) ceil( $metrics['ascender'] ) + 2, 0, $text );
			return $overlay;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function build_image_overlay_imagick( WatermarkRule $rule, int $canvas_width ): ?\Imagick {
		if ( null === $rule->overlay_path || ! is_readable( $rule->overlay_path ) ) {
			return null;
		}
		try {
			$overlay = new \Imagick( $rule->overlay_path );
			$target_width = (int) max( 1, round( $canvas_width * $rule->image_scale ) );
			$scale        = $target_width / max( 1, $overlay->getImageWidth() );
			$overlay->resizeImage(
				$target_width,
				(int) max( 1, round( $overlay->getImageHeight() * $scale ) ),
				\Imagick::FILTER_LANCZOS,
				1
			);
			return $overlay;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	// ------------------------------------------------------
	// GD path — fallback; simpler but loses some image data.
	// ------------------------------------------------------
	private function apply_with_gd( string $source_path, WatermarkRule $rule, int $image_type, string $text ): Result {
		try {
			$canvas = $this->gd_load( $source_path, $image_type );
			if ( null === $canvas ) {
				return Result::failure( $source_path, 'GD failed to load source.' );
			}

			$canvas_width  = imagesx( $canvas );
			$canvas_height = imagesy( $canvas );

			[ $overlay, $overlay_width, $overlay_height ] = 'text' === $rule->type
				? $this->build_text_overlay_gd( $rule, $text )
				: $this->build_image_overlay_gd( $rule, $canvas_width );

			if ( null === $overlay ) {
				imagedestroy( $canvas );
				return Result::failure( $source_path, 'Failed to build watermark overlay.' );
			}

			if ( 0 !== $rule->rotation ) {
				$rotated = imagerotate( $overlay, -$rule->rotation, imagecolorallocatealpha( $overlay, 0, 0, 0, 127 ) );
				if ( false !== $rotated ) {
					imagedestroy( $overlay );
					$overlay        = $rotated;
					$overlay_width  = imagesx( $overlay );
					$overlay_height = imagesy( $overlay );
				}
			}

			[ $x, $y ] = $rule->position->compute_xy(
				$canvas_width,
				$canvas_height,
				$overlay_width,
				$overlay_height,
				$rule->offset_x,
				$rule->offset_y,
				$rule->offset_unit
			);

			imagealphablending( $canvas, true );
			self::gd_copy_merge_alpha( $canvas, $overlay, $x, $y, 0, 0, $overlay_width, $overlay_height, $rule->opacity );

			$tmp_path = $this->tmp_sibling( $source_path );
			$saved    = $this->gd_save( $canvas, $tmp_path, $image_type );

			imagedestroy( $canvas );
			imagedestroy( $overlay );

			if ( ! $saved || ! Filesystem::move( $tmp_path, $source_path ) ) {
				Filesystem::delete( $tmp_path );
				return Result::failure( $source_path, 'GD failed to save watermarked output.' );
			}

			return Result::success( $source_path, 'gd' );
		} catch ( \Throwable $e ) {
			return Result::failure( $source_path, 'GD error: ' . $e->getMessage() );
		}
	}

	/** @return \GdImage|null */
	private function gd_load( string $path, int $type ) {
		return match ( $type ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $path ) ?: null,
			IMAGETYPE_PNG  => imagecreatefrompng( $path ) ?: null,
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? ( imagecreatefromwebp( $path ) ?: null ) : null,
			default        => null,
		};
	}

	private function gd_save( \GdImage $image, string $path, int $type ): bool {
		return match ( $type ) {
			IMAGETYPE_JPEG => imagejpeg( $image, $path, 90 ),
			IMAGETYPE_PNG  => imagepng( $image, $path, 6 ),
			IMAGETYPE_WEBP => function_exists( 'imagewebp' ) ? imagewebp( $image, $path, 85 ) : false,
			default        => false,
		};
	}

	/** @return array{0: \GdImage|null, 1: int, 2: int} */
	private function build_text_overlay_gd( WatermarkRule $rule, string $text ): array {
		[ $r, $g, $b ] = Color::hex_to_rgb( $rule->font_color );

		// Prefer TTF if we have a font path and imagettftext is available.
		if ( function_exists( 'imagettftext' ) && null !== $rule->font_path && is_readable( $rule->font_path ) ) {
			$bbox = imagettfbbox( $rule->font_size, 0, $rule->font_path, $text );
			if ( false === $bbox ) {
				return [ null, 0, 0 ];
			}
			$w = abs( $bbox[2] - $bbox[0] ) + 4;
			$h = abs( $bbox[7] - $bbox[1] ) + 4;
			$img = imagecreatetruecolor( $w, $h );
			imagealphablending( $img, false );
			imagesavealpha( $img, true );
			imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
			$color = imagecolorallocate( $img, $r, $g, $b );
			imagettftext( $img, $rule->font_size, 0, 2, $h - 2, $color, $rule->font_path, $text );
			return [ $img, $w, $h ];
		}

		// Bitmap font fallback: imagestring() with GD font 5 (largest built-in).
		$font_id = 5;
		$w = imagefontwidth( $font_id ) * max( 1, strlen( $text ) ) + 4;
		$h = imagefontheight( $font_id ) + 4;
		$img = imagecreatetruecolor( $w, $h );
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
		$color = imagecolorallocate( $img, $r, $g, $b );
		imagestring( $img, $font_id, 2, 2, $text, $color );
		return [ $img, $w, $h ];
	}

	/** @return array{0: \GdImage|null, 1: int, 2: int} */
	private function build_image_overlay_gd( WatermarkRule $rule, int $canvas_width ): array {
		if ( null === $rule->overlay_path || ! is_readable( $rule->overlay_path ) ) {
			return [ null, 0, 0 ];
		}
		$info = @getimagesize( $rule->overlay_path );
		if ( false === $info ) {
			return [ null, 0, 0 ];
		}
		[ $ow, $oh, $type ] = $info;
		$src = $this->gd_load( $rule->overlay_path, $type );
		if ( null === $src ) {
			return [ null, 0, 0 ];
		}
		$target_width = (int) max( 1, round( $canvas_width * $rule->image_scale ) );
		$scale        = $target_width / max( 1, $ow );
		$target_height = (int) max( 1, round( $oh * $scale ) );

		$dst = imagecreatetruecolor( $target_width, $target_height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagefill( $dst, 0, 0, imagecolorallocatealpha( $dst, 0, 0, 0, 127 ) );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $target_width, $target_height, $ow, $oh );
		imagedestroy( $src );
		return [ $dst, $target_width, $target_height ];
	}

	private function tmp_sibling( string $source_path ): string {
		return $source_path . '.markguard-tmp-' . bin2hex( random_bytes( 4 ) );
	}

	/**
	 * GD's imagecopymerge does not honor the source alpha channel; this helper
	 * composites through an intermediate truecolor image so both source alpha
	 * AND the opacity percentage are respected.
	 */
	private static function gd_copy_merge_alpha(
		\GdImage $dst_im,
		\GdImage $src_im,
		int $dst_x,
		int $dst_y,
		int $src_x,
		int $src_y,
		int $src_w,
		int $src_h,
		int $pct
	): bool {
		$pct = max( 0, min( 100, $pct ) );
		$cut = imagecreatetruecolor( $src_w, $src_h );
		imagealphablending( $cut, false );
		imagesavealpha( $cut, true );
		imagefill( $cut, 0, 0, imagecolorallocatealpha( $cut, 0, 0, 0, 127 ) );
		imagecopy( $cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h );
		imagecopy( $cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h );
		$ok = imagecopymerge( $dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct );
		imagedestroy( $cut );
		return $ok;
	}
}
