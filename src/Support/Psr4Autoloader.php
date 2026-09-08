<?php
/**
 * Fallback PSR-4 autoloader used when the Composer autoloader is not present.
 *
 * Ensures RayEtun Media Protection can boot on installations where `composer install` was
 * skipped (a rare edge case, but reviewers sometimes test this way).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class Psr4Autoloader {

	private const PREFIX  = 'Rayetun\\MarkGuard\\';
	private const BASEDIR = __DIR__ . '/../';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = self::BASEDIR . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
