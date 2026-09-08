<?php
/**
 * Data access for protected files.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Storage;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class ProtectedFileRepository {

	private ?AccessPolicyRepository $policies = null;

	private function policies(): AccessPolicyRepository {
		if ( null === $this->policies ) {
			$this->policies = new AccessPolicyRepository();
		}
		return $this->policies;
	}

	/**
	 * Insert a protected-file record. Returns the new row id, or 0 on failure.
	 * Pass $policy_id > 0 to bind a named policy; otherwise $policy is stored
	 * inline.
	 */
	public function insert(
		int $attachment_id,
		string $logical_key,
		string $storage_path,
		string $original_filename,
		string $backend,
		string $mime_type,
		int $size_bytes,
		AccessPolicy $policy,
		int $policy_id = 0
	): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::files_table(),
			[
				'attachment_id'     => $attachment_id,
				'logical_key'       => $logical_key,
				'storage_path'      => $storage_path,
				'original_filename' => $original_filename,
				'backend'           => $backend,
				'mime_type'         => $mime_type,
				'size_bytes'        => $size_bytes,
				'rule_set_id'       => $policy_id,
				'policy_json'       => (string) wp_json_encode( $policy->to_array() ),
				'created_at'        => $now,
				'updated_at'        => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public function find( int $id ): ?ProtectedFile {
		global $wpdb;
		$table = Schema::files_table();
		// Table name from trusted $wpdb->prefix; cannot be a bound parameter.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $row ? $this->hydrate( $row ) : null;
	}

	public function find_by_attachment( int $attachment_id ): ?ProtectedFile {
		global $wpdb;
		$table = Schema::files_table();
		// Table name from trusted $wpdb->prefix; cannot be a bound parameter.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE attachment_id = %d LIMIT 1", $attachment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * All protected files tied to an attachment (there can be several — a clean
	 * copy plus per-block download copies). Used to clean up on deletion.
	 *
	 * @return ProtectedFile[]
	 */
	public function all_by_attachment( int $attachment_id ): array {
		global $wpdb;
		$table = Schema::files_table();
		// Table name from trusted $wpdb->prefix; cannot be a bound parameter.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE attachment_id = %d", $attachment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return array_map( [ $this, 'hydrate' ], (array) $rows );
	}

	/**
	 * Assign an inline (custom) policy to a file, clearing any named-policy link.
	 */
	public function update_policy( int $id, AccessPolicy $policy ): bool {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::files_table(),
			[
				'rule_set_id' => 0,
				'policy_json' => (string) wp_json_encode( $policy->to_array() ),
				'updated_at'  => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Bind a file to a named policy (or 0 to detach and keep the inline policy).
	 */
	public function assign_named_policy( int $id, int $policy_id ): bool {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::files_table(),
			[
				'rule_set_id' => $policy_id,
				'updated_at'  => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Paginated list with optional filename search.
	 *
	 * @return array{items: ProtectedFile[], total: int}
	 */
	public function paginate( int $offset, int $limit, string $search = '' ): array {
		global $wpdb;
		$table  = Schema::files_table();
		$like   = '%' . $wpdb->esc_like( $search ) . '%';

		if ( '' !== $search ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE original_filename LIKE %s", $like ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE original_filename LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $limit, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		}

		return [
			'items' => array_map( [ $this, 'hydrate' ], (array) $rows ),
			'total' => $total,
		];
	}

	/**
	 * Served / blocked counts and last-access time for a file.
	 *
	 * @return array{served: int, blocked: int, last: ?string}
	 */
	public function activity( int $file_id ): array {
		global $wpdb;
		$log = Schema::log_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$served = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE file_id = %d AND event = 'served'", $file_id ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$blocked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE file_id = %d AND event IN ('denied','expired')", $file_id ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM `{$log}` WHERE file_id = %d", $file_id ) );

		return [
			'served'  => $served,
			'blocked' => $blocked,
			'last'    => is_string( $last ) ? $last : null,
		];
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::files_table(),
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Count how many times a specific link (identified by its nonce) has been
	 * successfully served, for click-limit enforcement.
	 */
	public function count_serves( int $file_id, string $link_nonce ): int {
		global $wpdb;
		$table = Schema::log_table();
		// Table name from trusted $wpdb->prefix; cannot be a bound parameter.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE file_id = %d AND link_nonce = %s AND event = 'served'", $file_id, $link_nonce ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/** @param array<string, mixed> $row */
	private function hydrate( array $row ): ProtectedFile {
		$policy_id   = (int) ( $row['rule_set_id'] ?? 0 );
		$policy_name = '';

		if ( $policy_id > 0 ) {
			$record = $this->policies()->find( $policy_id );
			if ( null !== $record ) {
				$policy      = $record->policy;
				$policy_name = $record->name;
			} else {
				// Named policy was deleted; fall back to no restrictions.
				$policy    = new AccessPolicy();
				$policy_id = 0;
			}
		} else {
			$policy_data = json_decode( (string) $row['policy_json'], true );
			$policy      = is_array( $policy_data ) ? AccessPolicy::from_array( $policy_data ) : new AccessPolicy();
		}

		return new ProtectedFile(
			id: (int) $row['id'],
			attachment_id: (int) $row['attachment_id'],
			logical_key: (string) $row['logical_key'],
			storage_path: (string) $row['storage_path'],
			original_filename: (string) $row['original_filename'],
			backend: (string) $row['backend'],
			mime_type: (string) $row['mime_type'],
			size_bytes: (int) $row['size_bytes'],
			policy: $policy,
			policy_id: $policy_id,
			policy_name: $policy_name
		);
	}
}
