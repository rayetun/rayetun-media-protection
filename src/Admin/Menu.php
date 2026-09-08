<?php
/**
 * Registers the RayEtun Media Protection admin menu. A single top-level page hosts the React
 * single-page app; the seven "screens" are client-side routes within it.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin;

use Rayetun\MarkGuard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG = 'rayetun-media-protection';

	private string $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'RayEtun Media Protection', 'rayetun-media-protection' ),
			__( 'Media Protection', 'rayetun-media-protection' ),
			Capabilities::MANAGE,
			self::SLUG,
			[ $this, 'render_page' ],
			$this->menu_icon(),
			71
		);

		( new Assets() )->bind( $this->hook_suffix );
	}

	public function hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * The React app mounts into this container. All UI is rendered client-side;
	 * a no-JS message keeps the page meaningful without scripts.
	 */
	public function render_page(): void {
		echo '<div class="wrap markguard-wrap">';
		echo '<div id="markguard-admin-app">';
		echo '<noscript>' . esc_html__( 'RayEtun Media Protection requires JavaScript to manage its settings.', 'rayetun-media-protection' ) . '</noscript>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Inline emerald shield SVG as a data URI, tinted to match the admin menu.
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad"><path d="M10 1.5 3 4v5c0 4.2 2.9 8.1 7 9.5 4.1-1.4 7-5.3 7-9.5V4l-7-2.5Zm0 2.1 5 1.8V9c0 3.2-2.1 6.2-5 7.4V3.6Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building a data: URI for the static admin-menu icon.
	}
}
