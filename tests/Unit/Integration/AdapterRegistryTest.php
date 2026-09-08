<?php
/**
 * Unit tests for the integration adapter registry and adapter metadata.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Integration\AdapterRegistry;
use Rayetun\MarkGuard\Integration\Contracts\Integration;

final class AdapterRegistryTest extends TestCase {

	public function test_register_and_get(): void {
		$registry = new AdapterRegistry();
		$adapter  = $this->fake_adapter( 'demo', true );
		$registry->register( $adapter );

		$this->assertSame( $adapter, $registry->get( 'demo' ) );
		$this->assertNull( $registry->get( 'missing' ) );
		$this->assertCount( 1, $registry->all() );
	}

	public function test_active_ids_reports_only_active_adapters(): void {
		$registry = new AdapterRegistry();
		$registry->register( $this->fake_adapter( 'on', true ) );
		$registry->register( $this->fake_adapter( 'off', false ) );

		$this->assertSame( [ 'on' ], $registry->active_ids() );
	}

	public function test_boot_active_only_hooks_active_adapters(): void {
		$registry = new AdapterRegistry();
		$active   = $this->fake_adapter( 'on', true );
		$inactive = $this->fake_adapter( 'off', false );
		$registry->register( $active );
		$registry->register( $inactive );

		$registry->boot_active();

		$this->assertTrue( $active->hooked );
		$this->assertFalse( $inactive->hooked );
	}

	private function fake_adapter( string $id, bool $active ): Integration {
		return new class( $id, $active ) implements Integration {
			public bool $hooked = false;

			public function __construct( private string $id, private bool $active ) {}

			public function id(): string {
				return $this->id;
			}

			public function label(): string {
				return $this->id;
			}

			public function is_active(): bool {
				return $this->active;
			}

			public function register_hooks(): void {
				$this->hooked = true;
			}
		};
	}
}
