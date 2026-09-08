<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the plugin's autoloader (falling back to our PSR-4 shim) so unit
 * tests can exercise MarkGuard classes without a full WordPress environment.
 * Constants normally set by WordPress or the plugin bootstrap are stubbed.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MARKGUARD_VERSION' ) ) {
	define( 'MARKGUARD_VERSION', '1.0.0-test' );
}
if ( ! defined( 'MARKGUARD_FILE' ) ) {
	define( 'MARKGUARD_FILE', dirname( __DIR__ ) . '/markguard.php' );
}
if ( ! defined( 'MARKGUARD_DIR' ) ) {
	define( 'MARKGUARD_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'MARKGUARD_URL' ) ) {
	define( 'MARKGUARD_URL', '' );
}
if ( ! defined( 'MARKGUARD_MIN_WP' ) ) {
	define( 'MARKGUARD_MIN_WP', '6.7' );
}
if ( ! defined( 'MARKGUARD_MIN_PHP' ) ) {
	define( 'MARKGUARD_MIN_PHP', '8.0' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

// Prefer Composer's autoloader if the caller has already run `composer install`.
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require dirname( __DIR__ ) . '/vendor/autoload.php';
} else {
	require dirname( __DIR__ ) . '/src/Support/Psr4Autoloader.php';
	\Rayetun\MarkGuard\Support\Psr4Autoloader::register();
}
