<?php
/**
 * `[markguard_clean_download id="123"]` — renders a button that lets an
 * authorized visitor download the clean (un-watermarked) original of a
 * watermarked attachment. Visitors who do not satisfy the file's access policy
 * see a short notice instead. The actual download is still served through the
 * signed, access-controlled handler.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Frontend;

use Rayetun\MarkGuard\Media\CleanCopyService;

defined( 'ABSPATH' ) || exit;

final class CleanDownloadShortcode {

	private const HANDLE = 'markguard-clean-download';

	public function register(): void {
		add_shortcode( 'markguard_clean_download', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_style' ] );
	}

	public function register_style(): void {
		wp_register_style(
			self::HANDLE,
			MARKGUARD_URL . 'assets/css/clean-download.css',
			[],
			MARKGUARD_VERSION
		);
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'id'         => '0',
				'label'      => __( 'Download original', 'rayetun-media-protection' ),
				'login_text' => __( 'Log in to download the original file.', 'rayetun-media-protection' ),
			],
			$atts,
			'markguard_clean_download'
		);

		$attachment_id = absint( $atts['id'] );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$service = new CleanCopyService();
		$file    = $service->file_for_attachment( $attachment_id );
		if ( null === $file ) {
			return '';
		}

		wp_enqueue_style( self::HANDLE );

		if ( ! $service->viewer_allowed( $file->policy ) ) {
			return sprintf(
				'<p class="markguard-clean-download markguard-clean-download--locked">%s</p>',
				esc_html( (string) $atts['login_text'] )
			);
		}

		return sprintf(
			'<a class="markguard-clean-download markguard-clean-download__button" href="%s" rel="nofollow">%s</a>',
			esc_url( $service->link( $file ) ),
			esc_html( (string) $atts['label'] )
		);
	}
}
