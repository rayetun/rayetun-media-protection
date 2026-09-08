<?php
/**
 * Return value from WatermarkEngine::apply().
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

final class Result {

	/**
	 * @param bool        $success     Watermark applied successfully.
	 * @param bool        $skipped     True when the source was skipped (e.g., below min size).
	 * @param string      $output_path Absolute path to the resulting file. Equals source_path when the engine overwrote in place.
	 * @param string      $engine_used Which backing implementation ran (e.g., 'imagick', 'gd').
	 * @param string|null $reason      Human-readable reason when skipped or failed.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly bool $skipped,
		public readonly string $output_path,
		public readonly string $engine_used,
		public readonly ?string $reason = null
	) {}

	public static function success( string $output_path, string $engine_used ): self {
		return new self( true, false, $output_path, $engine_used );
	}

	public static function skipped( string $source_path, string $reason ): self {
		return new self( false, true, $source_path, 'none', $reason );
	}

	public static function failure( string $source_path, string $reason ): self {
		return new self( false, false, $source_path, 'none', $reason );
	}
}
