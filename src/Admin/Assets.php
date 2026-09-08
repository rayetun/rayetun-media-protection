<?php
/**
 * Enqueues the compiled React admin bundle and bootstraps it with the REST
 * root, nonce, and initial data.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin;

use Rayetun\MarkGuard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private const HANDLE = 'markguard-admin';

	public function bind( string $hook_suffix ): void {
		add_action(
			'admin_enqueue_scripts',
			function ( string $hook ) use ( $hook_suffix ): void {
				if ( $hook !== $hook_suffix ) {
					return;
				}
				$this->enqueue();
			}
		);
	}

	private function enqueue(): void {
		$build = MARKGUARD_DIR . '/build';
		$asset = $build . '/index.asset.php';
		if ( ! is_readable( $asset ) ) {
			return; // Built assets missing; nothing to enqueue.
		}

		/** @var array{dependencies: string[], version: string} $meta */
		$meta = require $asset;

		// Media library — the watermark editor picks image overlays from it.
		wp_enqueue_media();

		wp_enqueue_script(
			self::HANDLE,
			MARKGUARD_URL . 'build/index.js',
			$meta['dependencies'],
			$meta['version'],
			true
		);

		wp_set_script_translations( self::HANDLE, 'rayetun-media-protection' );

		foreach ( [ 'index.css', 'style-index.css' ] as $css ) {
			if ( is_readable( $build . '/' . $css ) ) {
				wp_enqueue_style(
					self::HANDLE . '-' . sanitize_key( $css ),
					MARKGUARD_URL . 'build/' . $css,
					[ 'wp-components' ],
					$meta['version']
				);
			}
		}

		wp_localize_script(
			self::HANDLE,
			'markguardData',
			[
				'restUrl'      => esc_url_raw( rest_url( 'markguard/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'adminUrl'     => esc_url_raw( admin_url( 'admin.php?page=rayetun-media-protection' ) ),
				'supportUrl'   => 'https://wordpress.org/support/plugin/rayetun-media-protection/',
				'version'      => MARKGUARD_VERSION,
				'canAnalytics' => current_user_can( Capabilities::ANALYTICS ),
				'theme'        => $this->user_theme(),
			]
		);
	}

	private function user_theme(): string {
		$theme = (string) get_user_meta( get_current_user_id(), 'markguard_theme', true );
		return in_array( $theme, [ 'light', 'dark', 'auto' ], true ) ? $theme : 'auto';
	}
}
