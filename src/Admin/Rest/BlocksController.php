<?php
/**
 * Blocks REST controller — lists the registered RayEtun Media Protection blocks for the admin
 * "Blocks" hub, provides the shared policy list block editors need, and resolves
 * a picked attachment into a protected file for the download/library blocks.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Access\AccessPolicyRecord;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Blocks\BlockRegistry;
use Rayetun\MarkGuard\Blocks\DownloadConfig;
use Rayetun\MarkGuard\Blocks\LibraryConfig;
use Rayetun\MarkGuard\Blocks\ProtectedDownloadService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class BlocksController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/blocks',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/blocks/download/resolve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'resolve_download' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'attachmentId' => [ 'type' => 'integer', 'required' => true ],
					'policyId'     => [ 'type' => 'integer', 'default' => 0 ],
					'fileId'       => [ 'type' => 'integer', 'default' => 0 ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/blocks/defaults',
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
					'args'                => [
						'block'  => [ 'type' => 'string', 'required' => true, 'enum' => [ 'download', 'library' ] ],
						'config' => [ 'type' => 'object', 'required' => true ],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/blocks/library/resolve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'resolve_library' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'items'    => [ 'type' => 'array', 'required' => true ],
					'policyId' => [ 'type' => 'integer', 'default' => 0 ],
				],
			]
		);
	}

	public function get(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'blocks'   => BlockRegistry::all(),
				'policies' => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'id' => $r->id, 'name' => $r->name ],
					( new AccessPolicyRepository() )->all()
				),
			],
			200
		);
	}

	public function get_defaults(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'download' => DownloadConfig::defaults(),
				'library'  => LibraryConfig::defaults(),
				'policies' => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'id' => $r->id, 'name' => $r->name ],
					( new AccessPolicyRepository() )->all()
				),
			],
			200
		);
	}

	public function save_defaults( WP_REST_Request $request ): WP_REST_Response {
		$block  = (string) $request->get_param( 'block' );
		$config = (array) $request->get_param( 'config' );
		if ( 'download' === $block ) {
			DownloadConfig::save( $config );
		} elseif ( 'library' === $block ) {
			LibraryConfig::save( $config );
		}
		return $this->get_defaults();
	}

	public function resolve_download( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = absint( $request->get_param( 'attachmentId' ) );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Not a valid attachment.', 'rayetun-media-protection' ) ], 400 );
		}
		$data = ( new ProtectedDownloadService() )->resolve(
			$attachment_id,
			(int) $request->get_param( 'policyId' ), // may be -1 (Everyone); not absint.
			absint( $request->get_param( 'fileId' ) )
		);
		return new WP_REST_Response( $data, $data['ok'] ? 200 : 500 );
	}

	public function resolve_library( WP_REST_Request $request ): WP_REST_Response {
		$policy_id = (int) $request->get_param( 'policyId' ); // may be -1 (Everyone).
		$service   = new ProtectedDownloadService();
		$out       = [];
		foreach ( (array) $request->get_param( 'items' ) as $item ) {
			$attachment_id = absint( $item['attachmentId'] ?? 0 );
			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}
			$data                 = $service->resolve( $attachment_id, $policy_id, absint( $item['fileId'] ?? 0 ) );
			$data['attachmentId'] = $attachment_id;
			$out[]                = $data;
		}
		return new WP_REST_Response( [ 'items' => $out ], 200 );
	}
}
