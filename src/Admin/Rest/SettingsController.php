<?php
/**
 * Settings REST controller — feature toggles, onboarding, and the full settings
 * groups (general, uploads, capabilities, security, tools).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Access\AccessPolicyRecord;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Database\LogPruner;
use Rayetun\MarkGuard\Support\Capabilities;
use Rayetun\MarkGuard\Support\FeatureFlags;
use Rayetun\MarkGuard\Support\Signing;
use Rayetun\MarkGuard\Watermark\Preset;
use Rayetun\MarkGuard\Watermark\PresetRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class SettingsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/features', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'set_feature' ],
			'permission_callback' => [ $this, 'can_manage' ],
			'args'                => [
				'key'     => [ 'type' => 'string', 'required' => true, 'enum' => FeatureFlags::keys() ],
				'enabled' => [ 'type' => 'boolean', 'required' => true ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/onboarding/dismiss', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'dismiss_onboarding' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/settings/rotate-salt', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rotate_salt' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NAMESPACE, '/settings/export', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'export' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NAMESPACE, '/settings/import', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'import' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );
	}

	// --- Feature toggles + onboarding (unchanged from 8.1) ---

	public function set_feature( WP_REST_Request $request ): WP_REST_Response {
		$flags = new FeatureFlags();
		if ( ! $flags->set( (string) $request->get_param( 'key' ), (bool) $request->get_param( 'enabled' ) ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Unknown feature.', 'rayetun-media-protection' ) ], 400 );
		}
		return new WP_REST_Response( [ 'features' => $flags->all() ], 200 );
	}

	public function dismiss_onboarding(): WP_REST_Response {
		update_user_meta( get_current_user_id(), 'markguard_onboarding_dismissed', 1 );
		return new WP_REST_Response( [ 'dismissed' => true ], 200 );
	}

	// --- Settings groups (8.8) ---

	public function get_settings(): WP_REST_Response {
		$auto_protect    = get_option( 'markguard_auto_protect', [] );
		$clean_downloads = get_option( \Rayetun\MarkGuard\Media\CleanCopyService::OPTION, [] );

		return new WP_REST_Response(
			[
				'general'        => [
					'theme'            => $this->current_theme(),
					'logRetentionDays' => LogPruner::retention_days(),
				],
				'uploads'        => [
					'autoProtectPolicyId' => (int) ( is_array( $auto_protect ) ? ( $auto_protect['policyId'] ?? 0 ) : 0 ),
				],
				'cleanDownloads' => [
					'enabled'  => is_array( $clean_downloads ) && ! empty( $clean_downloads['enabled'] ),
					'policyId' => (int) ( is_array( $clean_downloads ) ? ( $clean_downloads['policyId'] ?? 0 ) : 0 ),
				],
				'capabilities' => $this->capability_roles(),
				'roles'        => $this->roles(),
				'policies'     => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'id' => $r->id, 'name' => $r->name ],
					( new AccessPolicyRepository() )->all()
				),
			],
			200
		);
	}

	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$group = (string) $request->get_param( 'group' );

		switch ( $group ) {
			case 'general':
				$this->save_theme( (string) $request->get_param( 'theme' ) );
				$days = max( 1, min( 3650, (int) $request->get_param( 'logRetentionDays' ) ) );
				update_option( LogPruner::OPTION_DAYS, $days, false );
				break;

			case 'uploads':
				$data             = get_option( 'markguard_auto_protect', [] );
				$data             = is_array( $data ) ? $data : [];
				$data['policyId'] = max( 0, (int) $request->get_param( 'autoProtectPolicyId' ) );
				update_option( 'markguard_auto_protect', $data, false );
				break;

			case 'cleanDownloads':
				update_option(
					\Rayetun\MarkGuard\Media\CleanCopyService::OPTION,
					[
						'enabled'  => (bool) $request->get_param( 'enabled' ),
						'policyId' => max( 0, (int) $request->get_param( 'policyId' ) ),
					],
					false
				);
				break;

			case 'capabilities':
				$this->save_capabilities(
					array_map( 'sanitize_key', (array) $request->get_param( 'manage' ) ),
					array_map( 'sanitize_key', (array) $request->get_param( 'analytics' ) )
				);
				break;

			default:
				return new WP_REST_Response( [ 'message' => __( 'Unknown settings group.', 'rayetun-media-protection' ) ], 400 );
		}

		return $this->get_settings();
	}

	public function rotate_salt(): WP_REST_Response {
		Signing::rotate();
		return new WP_REST_Response( [ 'rotated' => true ], 200 );
	}

	public function export(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'version'  => MARKGUARD_VERSION,
				'exported' => gmdate( 'c' ),
				'presets'  => array_map(
					static fn( Preset $p ): array => [ 'name' => $p->name, 'rule' => Preset::normalize( $p->config ), 'isDefaultUpload' => $p->is_default_upload ],
					( new PresetRepository() )->all()
				),
				'policies' => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'name' => $r->name, 'rule' => AccessPolicyRecord::policy_to_client( $r->policy ) ],
					( new AccessPolicyRepository() )->all()
				),
				'settings' => [
					'logRetentionDays' => LogPruner::retention_days(),
				],
			],
			200
		);
	}

	public function import( WP_REST_Request $request ): WP_REST_Response {
		$bundle = $request->get_json_params();
		if ( ! is_array( $bundle ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Invalid import file.', 'rayetun-media-protection' ) ], 400 );
		}

		$presets  = new PresetRepository();
		$policies = new AccessPolicyRepository();
		$counts   = [ 'presets' => 0, 'policies' => 0 ];

		foreach ( (array) ( $bundle['presets'] ?? [] ) as $p ) {
			if ( is_array( $p ) && isset( $p['name'], $p['rule'] ) ) {
				$presets->create( sanitize_text_field( (string) $p['name'] ), (array) $p['rule'], false );
				$counts['presets']++;
			}
		}
		foreach ( (array) ( $bundle['policies'] ?? [] ) as $p ) {
			if ( is_array( $p ) && isset( $p['name'], $p['rule'] ) ) {
				$policies->create( sanitize_text_field( (string) $p['name'] ), AccessPolicyRecord::policy_from_client( (array) $p['rule'] ) );
				$counts['policies']++;
			}
		}
		if ( isset( $bundle['settings']['logRetentionDays'] ) ) {
			update_option( LogPruner::OPTION_DAYS, max( 1, (int) $bundle['settings']['logRetentionDays'] ), false );
		}

		return new WP_REST_Response( [ 'imported' => $counts ], 200 );
	}

	// --- Helpers ---

	private function current_theme(): string {
		$theme = (string) get_user_meta( get_current_user_id(), 'markguard_theme', true );
		return in_array( $theme, [ 'light', 'dark', 'auto' ], true ) ? $theme : 'light';
	}

	private function save_theme( string $theme ): void {
		$theme = in_array( $theme, [ 'light', 'dark', 'auto' ], true ) ? $theme : 'light';
		update_user_meta( get_current_user_id(), 'markguard_theme', $theme );
	}

	/** @return array{manage: string[], analytics: string[]} */
	private function capability_roles(): array {
		$manage    = [];
		$analytics = [];
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$role = get_role( $slug );
			if ( ! $role instanceof \WP_Role ) {
				continue;
			}
			if ( $role->has_cap( Capabilities::MANAGE ) ) {
				$manage[] = $slug;
			}
			if ( $role->has_cap( Capabilities::ANALYTICS ) ) {
				$analytics[] = $slug;
			}
		}
		return [ 'manage' => $manage, 'analytics' => $analytics ];
	}

	/**
	 * @param string[] $manage
	 * @param string[] $analytics
	 */
	private function save_capabilities( array $manage, array $analytics ): void {
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$role = get_role( $slug );
			if ( ! $role instanceof \WP_Role ) {
				continue;
			}
			// The administrator role always keeps manage, to avoid lockout.
			$should_manage = in_array( $slug, $manage, true ) || 'administrator' === $slug;
			$this->apply_cap( $role, Capabilities::MANAGE, $should_manage );
			$this->apply_cap( $role, Capabilities::ANALYTICS, in_array( $slug, $analytics, true ) || 'administrator' === $slug );
		}
	}

	private function apply_cap( \WP_Role $role, string $cap, bool $grant ): void {
		if ( $grant ) {
			$role->add_cap( $cap );
		} else {
			$role->remove_cap( $cap );
		}
	}

	/** @return array<string, string> */
	private function roles(): array {
		$out = [];
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$out[ $slug ] = translate_user_role( $name );
		}
		return $out;
	}
}
