<?php
/**
 * RayEtun Media Protection uninstall handler.
 *
 * Fired when the plugin is deleted from wp-admin (not on deactivate).
 * Removes: three custom tables, plugin options, custom capabilities, scheduled
 * cron events, transients, and the protected-uploads directory. Attachment
 * posts and user media are never touched.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load the autoloader so we can call the Uninstaller class.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Support/Psr4Autoloader.php';
	\Rayetun\MarkGuard\Support\Psr4Autoloader::register();
}

\Rayetun\MarkGuard\Uninstaller::run();
