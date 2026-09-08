<?php
/**
 * Unit tests for the Watermark Registry.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Watermark;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Contracts\WatermarkEngine;
use Rayetun\MarkGuard\Watermark\Registry;
use Rayetun\MarkGuard\Watermark\Result;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

final class RegistryTest extends TestCase {

	public function test_registers_and_returns_by_id(): void {
		$registry = new Registry();
		$engine   = $this->make_engine( 'fake', [ 'image/png' ], true );

		$registry->register( 'fake', $engine );

		$this->assertSame( $engine, $registry->get( 'fake' ) );
		$this->assertNull( $registry->get( 'nonexistent' ) );
	}

	public function test_resolves_by_mime_type(): void {
		$registry = new Registry();
		$png_engine = $this->make_engine( 'png', [ 'image/png' ], true );
		$jpeg_engine = $this->make_engine( 'jpeg', [ 'image/jpeg' ], true );

		$registry->register( 'png', $png_engine );
		$registry->register( 'jpeg', $jpeg_engine );

		$this->assertSame( $png_engine, $registry->resolve_for_mime( 'image/png' ) );
		$this->assertSame( $jpeg_engine, $registry->resolve_for_mime( 'image/jpeg' ) );
		$this->assertNull( $registry->resolve_for_mime( 'application/pdf' ) );
	}

	public function test_skips_unavailable_engines_when_resolving(): void {
		$registry = new Registry();
		$unavailable = $this->make_engine( 'unavailable', [ 'image/png' ], false );
		$available   = $this->make_engine( 'available', [ 'image/png' ], true );

		$registry->register( 'unavailable', $unavailable );
		$registry->register( 'available', $available );

		$this->assertSame( $available, $registry->resolve_for_mime( 'image/png' ) );
	}

	/**
	 * @param string[] $mimes
	 */
	private function make_engine( string $id, array $mimes, bool $available ): WatermarkEngine {
		return new class( $id, $mimes, $available ) implements WatermarkEngine {
			public function __construct(
				private string $id,
				private array $mimes,
				private bool $available
			) {}

			public function id(): string {
				return $this->id;
			}

			public function label(): string {
				return $this->id;
			}

			public function supported_mime_types(): array {
				return $this->mimes;
			}

			public function is_available(): bool {
				return $this->available;
			}

			public function apply( string $source_path, WatermarkRule $rule, Context $context ): Result {
				return Result::success( $source_path, $this->id );
			}
		};
	}
}
