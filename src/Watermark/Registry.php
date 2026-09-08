<?php
/**
 * Registry of watermark engines. Populated by the
 * `markguard/register_watermark_engines` action; consumers resolve engines
 * either by id or by MIME type.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

use Rayetun\MarkGuard\Watermark\Contracts\WatermarkEngine;

defined( 'ABSPATH' ) || exit;

final class Registry {

	/** @var array<string, WatermarkEngine> */
	private array $engines = [];

	public function register( string $id, WatermarkEngine $engine ): void {
		$this->engines[ $id ] = $engine;
	}

	public function get( string $id ): ?WatermarkEngine {
		return $this->engines[ $id ] ?? null;
	}

	/**
	 * Resolve the first registered, available engine that accepts $mime_type.
	 */
	public function resolve_for_mime( string $mime_type ): ?WatermarkEngine {
		foreach ( $this->engines as $engine ) {
			if ( ! $engine->is_available() ) {
				continue;
			}
			if ( in_array( $mime_type, $engine->supported_mime_types(), true ) ) {
				return $engine;
			}
		}
		return null;
	}

	/** @return array<string, WatermarkEngine> */
	public function all(): array {
		return $this->engines;
	}
}
