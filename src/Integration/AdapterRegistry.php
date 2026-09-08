<?php
/**
 * Holds and boots e-commerce integration adapters.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration;

use Rayetun\MarkGuard\Integration\Contracts\Integration;

defined( 'ABSPATH' ) || exit;

final class AdapterRegistry {

	/** @var array<string, Integration> */
	private array $adapters = [];

	public function register( Integration $integration ): void {
		$this->adapters[ $integration->id() ] = $integration;
	}

	/** @return array<string, Integration> */
	public function all(): array {
		return $this->adapters;
	}

	public function get( string $id ): ?Integration {
		return $this->adapters[ $id ] ?? null;
	}

	/**
	 * Register hooks for every adapter whose target plugin is active.
	 */
	public function boot_active(): void {
		foreach ( $this->adapters as $adapter ) {
			if ( $adapter->is_active() ) {
				$adapter->register_hooks();
			}
		}
	}

	/** @return string[] Ids of adapters whose target plugin is active. */
	public function active_ids(): array {
		$active = [];
		foreach ( $this->adapters as $id => $adapter ) {
			if ( $adapter->is_active() ) {
				$active[] = $id;
			}
		}
		return $active;
	}
}
