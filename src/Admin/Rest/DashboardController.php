<?php
/**
 * Dashboard REST controller — stats and system health.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Database\Schema;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Storage\Backends\LocalBackend;
use Rayetun\MarkGuard\Support\FeatureFlags;
use Rayetun\MarkGuard\Support\ImagickDetector;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class DashboardController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);
	}

	public function get(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'stats'        => $this->stats(),
				'health'       => $this->health(),
				'features'     => ( new FeatureFlags() )->all(),
				'integrations' => Plugin::instance()->adapter_registry()->active_ids(),
				'onboarding'   => [
					'dismissed' => (bool) get_user_meta( get_current_user_id(), 'markguard_onboarding_dismissed', true ),
				],
			],
			200
		);
	}

	/** @return array<string, int> */
	private function stats(): array {
		global $wpdb;
		$files = Schema::files_table();
		$log   = Schema::log_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from trusted $wpdb->prefix.
		$protected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$files}`" );
		$served    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE event = 'served' AND created_at >= %s", $since ) );
		$blocked   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE event IN ('denied','expired') AND created_at >= %s", $since ) );
		// phpcs:enable

		return [
			'protectedFiles' => $protected,
			'served7d'       => $served,
			'blocked7d'      => $blocked,
			'presets'        => ( new \Rayetun\MarkGuard\Watermark\PresetRepository() )->count(),
		];
	}

	/** @return array<int, array{id:string,label:string,status:string,detail:string}> */
	private function health(): array {
		$root     = LocalBackend::protected_root();
		$writable = wp_is_writable( $root );
		$imagick  = ImagickDetector::is_available();
		$gd       = extension_loaded( 'gd' );
		$tcpdf    = class_exists( '\\setasign\\Fpdi\\Tcpdf\\Fpdi' );
		$cron     = (bool) wp_next_scheduled( Plugin::GC_CRON_HOOK );

		return [
			$this->row( 'image-engine', __( 'Image engine', 'rayetun-media-protection' ), $imagick || $gd, $imagick ? 'Imagick' : ( $gd ? 'GD (fallback)' : __( 'none available', 'rayetun-media-protection' ) ) ),
			$this->row( 'pdf-engine', __( 'PDF engine', 'rayetun-media-protection' ), $tcpdf, $tcpdf ? 'TCPDF + FPDI' : __( 'not loaded', 'rayetun-media-protection' ) ),
			$this->row( 'storage', __( 'Protected storage', 'rayetun-media-protection' ), $writable, $writable ? __( 'writable & guarded', 'rayetun-media-protection' ) : __( 'not writable', 'rayetun-media-protection' ) ),
			$this->row( 'cron', __( 'Cleanup schedule', 'rayetun-media-protection' ), $cron, $cron ? __( 'scheduled', 'rayetun-media-protection' ) : __( 'not scheduled', 'rayetun-media-protection' ) ),
		];
	}

	/** @return array{id:string,label:string,status:string,detail:string} */
	private function row( string $id, string $label, bool $ok, string $detail ): array {
		return [
			'id'     => $id,
			'label'  => $label,
			'status' => $ok ? 'ok' : 'warn',
			'detail' => $detail,
		];
	}
}
