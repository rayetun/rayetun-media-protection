<?php
/**
 * Analytics REST controller — aggregates the access log into the series the
 * Analytics screen charts: activity over time, busiest files, and the reasons
 * downloads were blocked.
 *
 * All figures are derived from RayEtun Media Protection's own log table; nothing leaves the
 * site. Timestamps are stored in UTC, so daily buckets are UTC days.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Database\Schema;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class AnalyticsController extends AbstractController {

	/** Ranges the UI may request, in days. */
	private const ALLOWED_RANGES = [ 7, 30, 90 ];

	private const TOP_FILES_LIMIT = 8;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analytics',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get' ],
				'permission_callback' => [ $this, 'can_view_analytics' ],
				'args'                => [
					'range' => [
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		$range = (int) $request->get_param( 'range' );
		if ( ! in_array( $range, self::ALLOWED_RANGES, true ) ) {
			$range = 30;
		}
		$since = gmdate( 'Y-m-d H:i:s', time() - $range * DAY_IN_SECONDS );

		return new WP_REST_Response(
			[
				'range'    => $range,
				'series'   => $this->series( $since, $range ),
				'topFiles' => $this->top_files( $since ),
				'denials'  => $this->denials( $since ),
				'totals'   => $this->totals( $since ),
			],
			200
		);
	}

	/**
	 * Daily served/blocked counts, gap-filled so every day in the range has a
	 * point (charts need a continuous x-axis, not just days that had activity).
	 *
	 * @return array<int, array{date:string, served:int, blocked:int}>
	 */
	private function series( string $since, int $range ): array {
		global $wpdb;
		$log = Schema::log_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from trusted $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d,
					SUM(CASE WHEN event = 'served' THEN 1 ELSE 0 END) AS served,
					SUM(CASE WHEN event IN ('denied','expired') THEN 1 ELSE 0 END) AS blocked
				FROM `{$log}`
				WHERE created_at >= %s
				GROUP BY DATE(created_at)",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_day = [];
		foreach ( (array) $rows as $row ) {
			$by_day[ $row['d'] ] = [
				'served'  => (int) $row['served'],
				'blocked' => (int) $row['blocked'],
			];
		}

		$series = [];
		for ( $i = $range - 1; $i >= 0; $i-- ) {
			$day = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$series[] = [
				'date'    => $day,
				'served'  => $by_day[ $day ]['served'] ?? 0,
				'blocked' => $by_day[ $day ]['blocked'] ?? 0,
			];
		}
		return $series;
	}

	/**
	 * Busiest files by total activity in the range.
	 *
	 * @return array<int, array{id:int, name:string, served:int, blocked:int}>
	 */
	private function top_files( string $since ): array {
		global $wpdb;
		$log   = Schema::log_table();
		$files = Schema::files_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from trusted $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.file_id AS id,
					f.original_filename AS name,
					SUM(CASE WHEN l.event = 'served' THEN 1 ELSE 0 END) AS served,
					SUM(CASE WHEN l.event IN ('denied','expired') THEN 1 ELSE 0 END) AS blocked
				FROM `{$log}` l
				LEFT JOIN `{$files}` f ON f.id = l.file_id
				WHERE l.created_at >= %s
				GROUP BY l.file_id
				ORDER BY (served + blocked) DESC, served DESC
				LIMIT %d",
				$since,
				self::TOP_FILES_LIMIT
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = [];
		foreach ( (array) $rows as $row ) {
			$name = (string) $row['name'];
			$out[] = [
				'id'      => (int) $row['id'],
				'name'    => '' !== $name ? $name : __( '(deleted file)', 'rayetun-media-protection' ),
				'served'  => (int) $row['served'],
				'blocked' => (int) $row['blocked'],
			];
		}
		return $out;
	}

	/**
	 * Blocked attempts grouped by reason, most common first.
	 *
	 * @return array<int, array{reason:string, label:string, count:int}>
	 */
	private function denials( string $since ): array {
		global $wpdb;
		$log = Schema::log_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from trusted $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT reason, COUNT(*) AS c
				FROM `{$log}`
				WHERE event IN ('denied','expired') AND created_at >= %s
				GROUP BY reason
				ORDER BY c DESC",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable

		$labels = $this->reason_labels();
		$out    = [];
		foreach ( (array) $rows as $row ) {
			$reason = (string) $row['reason'];
			$out[]  = [
				'reason' => '' !== $reason ? $reason : 'other',
				'label'  => $labels[ $reason ] ?? __( 'Other', 'rayetun-media-protection' ),
				'count'  => (int) $row['c'],
			];
		}
		return $out;
	}

	/** @return array{served:int, blocked:int, activeFiles:int, denialRate:float} */
	private function totals( string $since ): array {
		global $wpdb;
		$log = Schema::log_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from trusted $wpdb->prefix.
		$served = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE event = 'served' AND created_at >= %s", $since ) );
		$blocked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE event IN ('denied','expired') AND created_at >= %s", $since ) );
		$active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT file_id) FROM `{$log}` WHERE created_at >= %s", $since ) );
		// phpcs:enable

		$attempts = $served + $blocked;
		return [
			'served'      => $served,
			'blocked'     => $blocked,
			'activeFiles' => $active,
			'denialRate'  => $attempts > 0 ? round( $blocked / $attempts, 3 ) : 0.0,
		];
	}

	/**
	 * Human-readable labels for each block reason. Rule ids come from the
	 * access rules; 'token-exp' is logged for an expired signed token.
	 *
	 * @return array<string, string>
	 */
	private function reason_labels(): array {
		return [
			'role'        => __( 'Role not allowed', 'rayetun-media-protection' ),
			'logged-in'   => __( 'Not signed in', 'rayetun-media-protection' ),
			'hotlink'     => __( 'Hotlink blocked', 'rayetun-media-protection' ),
			'click-limit' => __( 'Click limit reached', 'rayetun-media-protection' ),
			'expiration'  => __( 'Link expired', 'rayetun-media-protection' ),
			'token-exp'   => __( 'Token expired', 'rayetun-media-protection' ),
			'other'       => __( 'Other', 'rayetun-media-protection' ),
		];
	}
}
