<?php
/**
 * The `markguard/protected-download` block: a signed, access-controlled download
 * for a single file of any type. Server-rendered so the link is minted per
 * request and the policy is enforced by the serve handler.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

use Rayetun\MarkGuard\Support\FileType;

defined( 'ABSPATH' ) || exit;

final class ProtectedDownloadBlock {

	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		$dir = MARKGUARD_DIR . '/build/blocks/protected-download';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $dir, [ 'render_callback' => [ $this, 'render' ] ] );
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes ): string {
		$file_id = absint( $attributes['fileId'] ?? 0 );
		if ( $file_id <= 0 ) {
			return '';
		}
		$data = ( new ProtectedDownloadService() )->render_data( $file_id );
		if ( null === $data ) {
			return '';
		}

		$label     = $this->text( $attributes['label'] ?? '', __( 'Download', 'rayetun-media-protection' ) );
		$locked    = $this->text( $attributes['lockedText'] ?? '', __( 'Log in to download', 'rayetun-media-protection' ) );
		$show_info = ! empty( $attributes['showInfo'] );
		$accent    = $this->accent( (string) ( $attributes['accent'] ?? '' ) );
		$template  = ( ( $attributes['template'] ?? 'card' ) === 'tile' ) ? 'tile' : 'card';

		$classes = [ 'mg-download', 'mg-download--' . $template ];
		/**
		 * Filters the download block wrapper classes.
		 *
		 * @param string[]             $classes
		 * @param array<string, mixed> $data
		 */
		$classes = (array) apply_filters( 'markguard_download_wrapper_class', $classes, $data );

		$out  = '<div class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '" style="--mg-accent:' . esc_attr( $accent ) . ';">';
		$out .= FileType::icon_svg( $data['mime'] );
		$out .= '<div class="mg-download__meta"><span class="mg-download__name">' . esc_html( $data['name'] ) . '</span>';
		if ( $show_info ) {
			$out .= '<span class="mg-download__info">' . esc_html( FileType::label( $data['mime'] ) . ' · ' . size_format( (int) $data['size'] ) ) . '</span>';
		}
		$out .= '</div>';
		$out .= DownloadAction::render( $data, $label, $locked );
		$out .= '</div>';

		/**
		 * Filters the final download block HTML.
		 *
		 * @param string               $out
		 * @param array<string, mixed> $attributes
		 * @param array<string, mixed> $data
		 */
		return (string) apply_filters( 'markguard_download_render', $out, $attributes, $data );
	}

	private function text( $value, string $fallback ): string {
		$value = sanitize_text_field( (string) $value );
		return '' !== $value ? $value : $fallback;
	}

	private function accent( string $color ): string {
		$hex = sanitize_hex_color( $color );
		return is_string( $hex ) && '' !== $hex ? $hex : '#059669';
	}
}
