<?php
/**
 * Watermark presets REST controller — CRUD plus a real-render preview.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Watermark\Preset;
use Rayetun\MarkGuard\Watermark\PresetRepository;
use Rayetun\MarkGuard\Watermark\SamplePreview;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class PresetsController extends AbstractController {

	private PresetRepository $repository;

	public function __construct() {
		$this->repository = new PresetRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/watermark-presets',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => $this->write_args(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/watermark-presets/(?P<id>\d+)',
			[
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => $this->write_args(),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/watermark-presets/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);
	}

	public function index(): WP_REST_Response {
		$presets = array_map(
			static fn( Preset $p ): array => $p->to_array(),
			$this->repository->all()
		);
		return new WP_REST_Response( [ 'presets' => $presets ], 200 );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$name    = $this->clean_name( (string) $request->get_param( 'name' ) );
		$config  = (array) $request->get_param( 'rule' );
		$default = (bool) $request->get_param( 'isDefaultUpload' );

		$id = $this->repository->create( $name, $config, $default );
		if ( 0 === $id ) {
			return new WP_REST_Response( [ 'message' => __( 'Could not save the preset.', 'rayetun-media-protection' ) ], 500 );
		}
		return new WP_REST_Response( $this->repository->find( $id )->to_array(), 201 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( null === $this->repository->find( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Preset not found.', 'rayetun-media-protection' ) ], 404 );
		}
		$name    = $this->clean_name( (string) $request->get_param( 'name' ) );
		$config  = (array) $request->get_param( 'rule' );
		$default = (bool) $request->get_param( 'isDefaultUpload' );

		$this->repository->update( $id, $name, $config, $default );
		return new WP_REST_Response( $this->repository->find( $id )->to_array(), 200 );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		$this->repository->delete( $id );
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$rule    = Preset::rule_from_config( (array) $request->get_param( 'rule' ) );
		$preview = ( new SamplePreview( Plugin::instance()->watermark_registry() ) )->render( $rule );

		if ( null === $preview ) {
			return new WP_REST_Response( [ 'message' => __( 'Preview could not be rendered on this server.', 'rayetun-media-protection' ) ], 422 );
		}
		return new WP_REST_Response( [ 'image' => $preview ], 200 );
	}

	private function clean_name( string $name ): string {
		$name = sanitize_text_field( $name );
		return '' !== $name ? $name : __( 'Untitled preset', 'rayetun-media-protection' );
	}

	/** @return array<string, array<string, mixed>> */
	private function write_args(): array {
		return [
			'name' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'rule' => [
				'type'     => 'object',
				'required' => true,
			],
			'isDefaultUpload' => [
				'type'    => 'boolean',
				'default' => false,
			],
		];
	}
}
