<?php
/**
 * PDF watermark engine — stamps text or an image overlay onto every page of a
 * PDF, with optional dynamic per-user tokens.
 *
 * Reads the source with FPDI (importing each existing page as a template) and
 * draws the watermark on top with TCPDF. Non-destructive by contract: writes
 * to a sibling temp file, then replaces the source via WP_Filesystem.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark\Engines;

use Rayetun\MarkGuard\Support\Color;
use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Contracts\WatermarkEngine;
use Rayetun\MarkGuard\Watermark\Result;
use Rayetun\MarkGuard\Watermark\TokenRegistry;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

defined( 'ABSPATH' ) || exit;

final class PdfEngine implements WatermarkEngine {

	private const FPDI_CLASS = '\\setasign\\Fpdi\\Tcpdf\\Fpdi';

	public function __construct(
		private readonly ?TokenRegistry $tokens = null
	) {}

	public function id(): string {
		return 'pdf';
	}

	public function label(): string {
		return __( 'PDF documents', 'rayetun-media-protection' );
	}

	public function supported_mime_types(): array {
		return [ 'application/pdf' ];
	}

	public function is_available(): bool {
		return class_exists( self::FPDI_CLASS );
	}

	public function apply( string $source_path, WatermarkRule $rule, Context $context ): Result {
		if ( ! $this->is_available() ) {
			return Result::failure( $source_path, 'TCPDF/FPDI is not available (composer dependencies missing).' );
		}
		if ( ! is_readable( $source_path ) ) {
			return Result::failure( $source_path, sprintf( 'Source not readable: %s', $source_path ) );
		}

		$text = 'text' === $rule->type
			? ( $this->tokens?->resolve( $rule->text, $context ) ?? $rule->text )
			: '';

		if ( 'text' === $rule->type && '' === trim( $text ) ) {
			return Result::failure( $source_path, 'Text watermark resolved to an empty string.' );
		}
		if ( 'image' === $rule->type && ( null === $rule->overlay_path || ! is_readable( (string) $rule->overlay_path ) ) ) {
			return Result::failure( $source_path, 'Image overlay path is not readable.' );
		}

		try {
			$fpdi_class = self::FPDI_CLASS;
			/** @var \setasign\Fpdi\Tcpdf\Fpdi $pdf */
			$pdf = new $fpdi_class( 'P', 'mm', 'A4', true, 'UTF-8', false );
			$pdf->setPrintHeader( false );
			$pdf->setPrintFooter( false );
			$pdf->SetAutoPageBreak( false );
			$pdf->SetCreator( 'RayEtun Media Protection' );

			$page_count = $pdf->setSourceFile( $source_path );
			if ( $page_count < 1 ) {
				return Result::failure( $source_path, 'Source PDF has no importable pages.' );
			}

			for ( $page_no = 1; $page_no <= $page_count; $page_no++ ) {
				$template_id = $pdf->importPage( $page_no );
				$size        = $pdf->getTemplateSize( $template_id );

				$page_w = (float) ( $size['width'] ?? $size['w'] ?? 0 );
				$page_h = (float) ( $size['height'] ?? $size['h'] ?? 0 );
				if ( $page_w <= 0 || $page_h <= 0 ) {
					continue;
				}

				$orientation = $page_w > $page_h ? 'L' : 'P';
				$pdf->AddPage( $orientation, [ $page_w, $page_h ] );
				$pdf->useTemplate( $template_id );

				$pdf->SetAlpha( max( 0.0, min( 1.0, $rule->opacity / 100 ) ) );
				if ( 'text' === $rule->type ) {
					$this->draw_text( $pdf, $text, $rule, $page_w, $page_h );
				} else {
					$this->draw_image( $pdf, $rule, $page_w, $page_h );
				}
				$pdf->SetAlpha( 1.0 );
			}

			$tmp_path = $source_path . '.markguard-tmp-' . bin2hex( random_bytes( 4 ) );
			$pdf->Output( $tmp_path, 'F' );

			if ( ! is_readable( $tmp_path ) ) {
				return Result::failure( $source_path, 'PDF output was not written.' );
			}
			if ( ! Filesystem::move( $tmp_path, $source_path ) ) {
				Filesystem::delete( $tmp_path );
				return Result::failure( $source_path, 'Failed to replace source with watermarked PDF.' );
			}

			return Result::success( $source_path, 'tcpdf-fpdi' );
		} catch ( \Throwable $e ) {
			return Result::failure( $source_path, 'PDF error: ' . $e->getMessage() );
		}
	}

	/**
	 * @param \setasign\Fpdi\Tcpdf\Fpdi $pdf
	 */
	private function draw_text( $pdf, string $text, WatermarkRule $rule, float $page_w, float $page_h ): void {
		$pdf->SetFont( 'helvetica', '', $rule->font_size );
		[ $r, $g, $b ] = Color::hex_to_rgb( $rule->font_color );
		$pdf->SetTextColor( $r, $g, $b );

		$text_w = (float) $pdf->GetStringWidth( $text );
		$text_h = $rule->font_size * 0.3528; // Approx pt → mm.

		[ $x, $y ] = $rule->position->compute_xy(
			(int) round( $page_w ),
			(int) round( $page_h ),
			(int) ceil( $text_w ),
			(int) ceil( $text_h ),
			$rule->offset_x,
			$rule->offset_y,
			$rule->offset_unit
		);

		if ( 0 !== $rule->rotation ) {
			$pdf->StartTransform();
			$pdf->Rotate( $rule->rotation, $x + ( $text_w / 2 ), $y + ( $text_h / 2 ) );
			$pdf->Text( $x, $y, $text );
			$pdf->StopTransform();
			return;
		}
		$pdf->Text( $x, $y, $text );
	}

	/**
	 * @param \setasign\Fpdi\Tcpdf\Fpdi $pdf
	 */
	private function draw_image( $pdf, WatermarkRule $rule, float $page_w, float $page_h ): void {
		$overlay = (string) $rule->overlay_path;
		$info    = @getimagesize( $overlay );
		if ( false === $info ) {
			return;
		}
		[ $img_w_px, $img_h_px ] = $info;
		if ( $img_w_px <= 0 || $img_h_px <= 0 ) {
			return;
		}

		$target_w = $page_w * $rule->image_scale;
		$target_h = $target_w * ( $img_h_px / $img_w_px );

		[ $x, $y ] = $rule->position->compute_xy(
			(int) round( $page_w ),
			(int) round( $page_h ),
			(int) ceil( $target_w ),
			(int) ceil( $target_h ),
			$rule->offset_x,
			$rule->offset_y,
			$rule->offset_unit
		);

		if ( 0 !== $rule->rotation ) {
			$pdf->StartTransform();
			$pdf->Rotate( $rule->rotation, $x + ( $target_w / 2 ), $y + ( $target_h / 2 ) );
			$pdf->Image( $overlay, $x, $y, $target_w, $target_h );
			$pdf->StopTransform();
			return;
		}
		$pdf->Image( $overlay, $x, $y, $target_w, $target_h );
	}
}
