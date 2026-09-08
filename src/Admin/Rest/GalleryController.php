<?php
/**
 * Gallery REST controller — resolves attachment ids into their protected
 * display assets (clean thumbnail + watermarked preview + downloadable flag)
 * for the block editor, and reads/writes the backend-editable gallery defaults.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Access\AccessPolicyRecord;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Gallery\GalleryAssetService;
use Rayetun\MarkGuard\Gallery\GalleryConfig;
use Rayetun\MarkGuard\Watermark\Preset;
use Rayetun\MarkGuard\Watermark\PresetRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class GalleryController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/gallery/resolve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'resolve' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'ids'        => [ 'type' => 'array', 'required' => true, 'items' => [ 'type' => 'integer' ] ],
					'presetId'   => [ 'type' => 'integer', 'default' => 0 ],
					'thumbMax'   => [ 'type' => 'integer', 'default' => GalleryAssetService::DEFAULT_THUMB_MAX ],
					'previewMax' => [ 'type' => 'integer', 'default' => GalleryAssetService::DEFAULT_PREVIEW_MAX ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/gallery/defaults',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_defaults' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_defaults' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
			]
		);
	}

	public function resolve( WP_REST_Request $request ): WP_REST_Response {
		$ids         = array_map( 'absint', (array) $request->get_param( 'ids' ) );
		$preset_id   = (int) $request->get_param( 'presetId' );
		$thumb_max   = (int) $request->get_param( 'thumbMax' );
		$preview_max = (int) $request->get_param( 'previewMax' );

		$service = new GalleryAssetService();
		$assets  = [];
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$data       = $service->ensure( $id, $preset_id, $thumb_max, $preview_max );
			$data['id'] = $id;

			/**
			 * Filters a single resolved gallery asset. Pro can add fields.
			 *
			 * @param array<string, mixed> $data
			 * @param int                  $id
			 */
			$assets[] = apply_filters( 'markguard_gallery_asset_data', $data, $id );
		}

		return new WP_REST_Response( [ 'assets' => $assets ], 200 );
	}

	public function get_defaults(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'config'        => GalleryConfig::global_defaults(),
				'layouts'       => GalleryConfig::layouts(),
				'overlayStyles' => GalleryConfig::overlay_styles(),
				'presets'       => array_map(
					static fn( Preset $p ): array => [ 'id' => $p->id, 'name' => $p->name ],
					( new PresetRepository() )->all()
				),
				'policies'      => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'id' => $r->id, 'name' => $r->name ],
					( new AccessPolicyRepository() )->all()
				),
			],
			200
		);
	}

	public function save_defaults( WP_REST_Request $request ): WP_REST_Response {
		$config = $request->get_param( 'config' );
		if ( ! is_array( $config ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Invalid config.', 'rayetun-media-protection' ) ], 400 );
		}
		GalleryConfig::save_global_defaults( $config );
		return new WP_REST_Response( [ 'config' => GalleryConfig::global_defaults() ], 200 );
	}
}
