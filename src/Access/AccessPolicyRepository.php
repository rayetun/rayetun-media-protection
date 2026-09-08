<?php
/**
 * Data access for reusable access policies.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

use Rayetun\MarkGuard\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class AccessPolicyRepository {

	/** @return AccessPolicyRecord[] */
	public function all(): array {
		global $wpdb;
		$table = Schema::policies_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY name ASC", ARRAY_A );
		return array_map( [ $this, 'hydrate' ], (array) $rows );
	}

	public function find( int $id ): ?AccessPolicyRecord {
		global $wpdb;
		$table = Schema::policies_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	public function create( string $name, AccessPolicy $policy ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::policies_table(),
			[
				'name'        => $name,
				'policy_json' => (string) wp_json_encode( $policy->to_array() ),
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public function update( int $id, string $name, AccessPolicy $policy ): bool {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::policies_table(),
			[
				'name'        => $name,
				'policy_json' => (string) wp_json_encode( $policy->to_array() ),
				'updated_at'  => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::policies_table(),
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/** @param array<string, mixed> $row */
	private function hydrate( array $row ): AccessPolicyRecord {
		$data   = json_decode( (string) $row['policy_json'], true );
		$policy = is_array( $data ) ? AccessPolicy::from_array( $data ) : new AccessPolicy();
		return new AccessPolicyRecord(
			id: (int) $row['id'],
			name: (string) $row['name'],
			policy: $policy
		);
	}
}
