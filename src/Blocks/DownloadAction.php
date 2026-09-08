<?php
/**
 * Renders the action shown for a protected file: a download button when the
 * viewer is allowed, a "log in" button (linking to the login form, returning to
 * this page) when they are not signed in, or a plain notice when they are signed
 * in but still lack access.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

defined( 'ABSPATH' ) || exit;

final class DownloadAction {

	/**
	 * @param array{downloadable:bool, downloadUrl:string} $data
	 */
	public static function render( array $data, string $download_label, string $locked_label ): string {
		if ( ! empty( $data['downloadable'] ) ) {
			return '<a class="mg-download__button" rel="nofollow" href="' . esc_url( (string) $data['downloadUrl'] ) . '">' . esc_html( $download_label ) . '</a>';
		}

		if ( ! is_user_logged_in() ) {
			$redirect = get_permalink();
			$login    = wp_login_url( $redirect ? $redirect : home_url( '/' ) );
			return '<a class="mg-download__button mg-download__button--login" href="' . esc_url( $login ) . '">' . esc_html( $locked_label ) . '</a>';
		}

		return '<span class="mg-download__locked">' . esc_html( $locked_label ) . '</span>';
	}
}
