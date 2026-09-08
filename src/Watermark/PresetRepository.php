<?php
/**
 * Data access for watermark presets.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

use Rayetun\MarkGuard\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class PresetRepository {

	/** @return Preset[] */
	public function all(): array {
		global $wpdb;
		$table = Schema::presets_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY name ASC", ARRAY_A );
		return array_map( [ $this, 'hydrate' ], (array) $rows );
	}

	public function count(): int {
		global $wpdb;
		$table = Schema::presets_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	}

	public function find( int $id ): ?Preset {
		global $wpdb;
		$table = Schema::presets_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	/** @param array<string, mixed> $config */
	public function create( string $name, array $config, bool $is_default_upload ): int {
		global $wpdb;
		if ( $is_default_upload ) {
			$this->clear_default();
		}
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::presets_table(),
			[
				'name'              => $name,
				'config_json'       => (string) wp_json_encode( Preset::normalize( $config ) ),
				'is_default_upload' => $is_default_upload ? 1 : 0,
				'created_at'        => $now,
				'updated_at'        => $now,
			],
			[ '%s', '%s', '%d', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/** @param array<string, mixed> $config */
	public function update( int $id, string $name, array $config, bool $is_default_upload ): bool {
		global $wpdb;
		if ( $is_default_upload ) {
			$this->clear_default();
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::presets_table(),
			[
				'name'              => $name,
				'config_json'       => (string) wp_json_encode( Preset::normalize( $config ) ),
				'is_default_upload' => $is_default_upload ? 1 : 0,
				'updated_at'        => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::presets_table(),
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	public function default_upload_preset(): ?Preset {
		global $wpdb;
		$table = Schema::presets_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table name.
		$row = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE is_default_upload = 1 LIMIT 1", ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	private function clear_default(): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::presets_table(),
			[ 'is_default_upload' => 0 ],
			[ 'is_default_upload' => 1 ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	/** @param array<string, mixed> $row */
	private function hydrate( array $row ): Preset {
		$config = json_decode( (string) $row['config_json'], true );
		return new Preset(
			id: (int) $row['id'],
			name: (string) $row['name'],
			config: is_array( $config ) ? $config : [],
			is_default_upload: (bool) $row['is_default_upload']
		);
	}
}
