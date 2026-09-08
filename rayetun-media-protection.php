<?php
/**
 * Plugin Name:       RayEtun Media Protection - Watermark and File Access Control
 * Plugin URI:        https://wordpress.org/plugins/rayetun-media-protection/
 * Description:       Watermark images and PDFs, protect downloadable files, and control who can access them. Built on standard WordPress APIs — REST, the block editor, and @wordpress/components.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            Md Rayhan Uddin
 * Author URI:        https://rayetun.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rayetun-media-protection
 * Domain Path:       /languages
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'MARKGUARD_VERSION', '1.0.0' );
define( 'MARKGUARD_FILE', __FILE__ );
define( 'MARKGUARD_DIR', __DIR__ );
define( 'MARKGUARD_URL', plugin_dir_url( __FILE__ ) );
define( 'MARKGUARD_MIN_WP', '6.7' );
define( 'MARKGUARD_MIN_PHP', '8.0' );

// Environment guard — must load without autoloader in case Composer is missing.
require_once __DIR__ . '/src/Support/EnvironmentGuard.php';

if ( ! \Rayetun\MarkGuard\Support\EnvironmentGuard::meets_requirements() ) {
	\Rayetun\MarkGuard\Support\EnvironmentGuard::register_admin_notice();
	return; // Silent skip; admin notice explains why.
}

// Autoloader — Composer if present, hand-rolled PSR-4 fallback otherwise.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Support/Psr4Autoloader.php';
	\Rayetun\MarkGuard\Support\Psr4Autoloader::register();
}

// Boot at plugins_loaded priority 5, before default-priority integrations.
add_action(
	'plugins_loaded',
	static function (): void {
		\Rayetun\MarkGuard\Plugin::instance()->boot();
	},
	5
);

// Activation and deactivation are static; uninstall is handled by uninstall.php.
register_activation_hook( __FILE__, [ \Rayetun\MarkGuard\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Rayetun\MarkGuard\Plugin::class, 'deactivate' ] );
