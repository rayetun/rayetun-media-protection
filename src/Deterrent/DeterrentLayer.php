<?php
/**
 * Front-end deterrent layer. When the "Deterrent layer" feature is on, this
 * loads a tiny, dependency-free script that discourages casual copying of
 * images and documents: it blocks the right-click menu and drag on images and
 * intercepts the common save / view-source / devtools keyboard shortcuts.
 *
 * These are *deterrents*, not protection — anyone determined can still reach the
 * bytes. The real guarantee is RayEtun Media Protection's signed, access-controlled file
 * serving and watermarking; the UI says as much. Kept as plain JS/CSS so no
 * framework runtime is shipped to every front-end page.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Deterrent;

defined( 'ABSPATH' ) || exit;

final class DeterrentLayer {

	private const HANDLE = 'markguard-deterrents';

	private const OPTION = 'markguard_deterrents';

	public function register(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'body_class', [ $this, 'body_class' ] );
	}

	private function is_enabled(): bool {
		$data    = get_option( self::OPTION, [] );
		$enabled = is_array( $data ) && ! empty( $data['enabled'] );

		/**
		 * Filters whether the front-end deterrent layer should load for the
		 * current request. Lets a site exclude logged-in editors, specific
		 * templates, etc.
		 *
		 * @param bool $enabled Whether the master toggle is on.
		 */
		return (bool) apply_filters( 'markguard_deterrents_should_load', $enabled );
	}

	/**
	 * The per-behaviour config handed to the script. Sub-flags default on; a
	 * filter (or future settings) can turn individual behaviours off.
	 *
	 * @return array{rightClick:bool, dragDrop:bool, keyboard:bool, message:string}
	 */
	private function config(): array {
		$data = get_option( self::OPTION, [] );
		$data = is_array( $data ) ? $data : [];

		$config = [
			'rightClick' => array_key_exists( 'rightClick', $data ) ? (bool) $data['rightClick'] : true,
			'dragDrop'   => array_key_exists( 'dragDrop', $data ) ? (bool) $data['dragDrop'] : true,
			'keyboard'   => array_key_exists( 'keyboard', $data ) ? (bool) $data['keyboard'] : true,
			'message'    => __( 'This content is protected.', 'rayetun-media-protection' ),
		];

		/**
		 * Filters the deterrent behaviours and the notice text.
		 *
		 * @param array{rightClick:bool, dragDrop:bool, keyboard:bool, message:string} $config
		 */
		return apply_filters( 'markguard_deterrent_config', $config );
	}

	public function enqueue(): void {
		wp_enqueue_style(
			self::HANDLE,
			MARKGUARD_URL . 'assets/css/deterrents.css',
			[],
			MARKGUARD_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			MARKGUARD_URL . 'assets/js/deterrents.js',
			[],
			MARKGUARD_VERSION,
			true
		);

		wp_localize_script( self::HANDLE, 'markguardDeterrents', $this->config() );
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		$classes[] = 'markguard-deterrents-on';
		return $classes;
	}
}
