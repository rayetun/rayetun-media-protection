<?php
/**
 * Integrations REST controller — reports which e-commerce plugins are active
 * and manages the shared e-commerce watermark (on/off + which preset).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Integration\Contracts\Integration;
use Rayetun\MarkGuard\Integration\WatermarkSettings;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Watermark\Preset;
use Rayetun\MarkGuard\Watermark\PresetRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class IntegrationsController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/integrations',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'enabled'  => [
							'type'     => 'boolean',
							'required' => true,
						],
						'presetId' => [
							'type'    => 'integer',
							'default' => 0,
						],
					],
				],
			]
		);
	}

	public function index(): WP_REST_Response {
		$registry = Plugin::instance()->adapter_registry();
		$settings = new WatermarkSettings();

		$integrations = array_map(
			static fn( Integration $i ): array => [
				'id'     => $i->id(),
				'label'  => $i->label(),
				'active' => $i->is_active(),
			],
			array_values( $registry->all() )
		);

		$presets = array_map(
			static fn( Preset $p ): array => [ 'id' => $p->id, 'name' => $p->name ],
			( new PresetRepository() )->all()
		);

		return new WP_REST_Response(
			[
				'integrations' => $integrations,
				'config'       => [
					'enabled'  => $settings->is_enabled(),
					'presetId' => $settings->preset_id(),
				],
				'presets'      => $presets,
			],
			200
		);
	}

	public function save( WP_REST_Request $request ): WP_REST_Response {
		$settings = new WatermarkSettings();
		$settings->save( (bool) $request->get_param( 'enabled' ), (int) $request->get_param( 'presetId' ) );
		return new WP_REST_Response(
			[
				'enabled'  => $settings->is_enabled(),
				'presetId' => $settings->preset_id(),
			],
			200
		);
	}
}
