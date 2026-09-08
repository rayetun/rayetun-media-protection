<?php
/**
 * Watermark engine contract.
 *
 * Implementations are registered via the `markguard/register_watermark_engines`
 * action so the pro plugin (or third-party code) can add engines without
 * touching RayEtun Media Protection core.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark\Contracts;

use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Result;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

defined( 'ABSPATH' ) || exit;

interface WatermarkEngine {

	/** Machine id (matches Registry key). */
	public function id(): string;

	/** Human-readable label for admin UIs. */
	public function label(): string;

	/**
	 * MIME types this engine can handle.
	 *
	 * @return string[]
	 */
	public function supported_mime_types(): array;

	/** True when the engine can run on the current server (extensions loaded, libs available). */
	public function is_available(): bool;

	/**
	 * Apply the watermark to $source_path and return a Result describing
	 * the outcome. Implementations MAY overwrite $source_path in place OR
	 * write to a new path; callers must inspect Result::$output_path.
	 */
	public function apply( string $source_path, WatermarkRule $rule, Context $context ): Result;
}
