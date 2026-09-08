<?php
/**
 * Protected files REST controller — list, assign policy, mint links, protect a
 * media attachment, and delete.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Access\AccessPolicyRecord;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Access\LinkFactory;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Storage\ProtectedFile;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class FilesController extends AbstractController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/files',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/files/protect',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'protect' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'attachmentId' => [
						'type'     => 'integer',
						'required' => true,
					],
					'policyId'     => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/files/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'set_policy' ],
					'permission_callback' => [ $this, 'can_manage' ],
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
			'/files/(?P<id>\d+)/links',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'mint_link' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'ttl' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$repo   = Plugin::instance()->protected_file_repository();
		$result = $repo->paginate( ( $page - 1 ) * $per_page, $per_page, $search );

		$items = array_map(
			fn( ProtectedFile $f ): array => $this->to_client( $f ),
			$result['items']
		);

		return new WP_REST_Response(
			[
				'files'    => $items,
				'total'    => $result['total'],
				'page'     => $page,
				'perPage'  => $per_page,
				'policies' => array_map(
					static fn( AccessPolicyRecord $r ): array => [ 'id' => $r->id, 'name' => $r->name ],
					( new AccessPolicyRepository() )->all()
				),
			],
			200
		);
	}

	public function protect( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'attachmentId' );
		$policy_id     = (int) $request->get_param( 'policyId' );

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return new WP_REST_Response( [ 'message' => __( 'That attachment file could not be found.', 'rayetun-media-protection' ) ], 404 );
		}

		$plugin      = Plugin::instance();
		$backend     = $plugin->storage_backend( 'local' );
		$repo        = $plugin->protected_file_repository();
		$logical_key = bin2hex( random_bytes( 16 ) );

		$storage_path = $backend->store( $path, $logical_key );
		if ( null === $storage_path ) {
			return new WP_REST_Response( [ 'message' => __( 'Could not move the file into protected storage.', 'rayetun-media-protection' ) ], 500 );
		}

		$policy = ( $policy_id > 0 )
			? ( ( new AccessPolicyRepository() )->find( $policy_id )->policy ?? new \Rayetun\MarkGuard\Access\AccessPolicy() )
			: new \Rayetun\MarkGuard\Access\AccessPolicy();

		$id = $repo->insert(
			$attachment_id,
			$logical_key,
			$storage_path,
			basename( $path ),
			$backend->id(),
			(string) ( get_post_mime_type( $attachment_id ) ?: 'application/octet-stream' ),
			(int) ( @filesize( $storage_path ) ?: 0 ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort size.
			$policy,
			$policy_id
		);

		if ( 0 === $id ) {
			return new WP_REST_Response( [ 'message' => __( 'File stored but could not be recorded.', 'rayetun-media-protection' ) ], 500 );
		}

		return new WP_REST_Response( $this->to_client( $repo->find( $id ) ), 201 );
	}

	public function set_policy( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$repo = Plugin::instance()->protected_file_repository();
		if ( null === $repo->find( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'File not found.', 'rayetun-media-protection' ) ], 404 );
		}

		$policy_id = (int) $request->get_param( 'policyId' );
		if ( $policy_id > 0 ) {
			$repo->assign_named_policy( $id, $policy_id );
		} else {
			$custom = (array) $request->get_param( 'custom' );
			$repo->update_policy( $id, AccessPolicyRecord::policy_from_client( $custom ) );
		}

		return new WP_REST_Response( $this->to_client( $repo->find( $id ) ), 200 );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$plugin  = Plugin::instance();
		$repo    = $plugin->protected_file_repository();
		$file    = $repo->find( $id );
		if ( null !== $file ) {
			$plugin->storage_backend( $file->backend )->delete( $file->storage_path );
			$repo->delete( $id );
		}
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	public function mint_link( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$ttl  = (int) $request->get_param( 'ttl' );
		$repo = Plugin::instance()->protected_file_repository();
		$file = $repo->find( $id );
		if ( null === $file ) {
			return new WP_REST_Response( [ 'message' => __( 'File not found.', 'rayetun-media-protection' ) ], 404 );
		}
		$url = ( new LinkFactory() )->create( $file, $ttl > 0 ? $ttl : null );
		return new WP_REST_Response( [ 'url' => $url, 'ttl' => $ttl ], 201 );
	}

	/** @return array<string, mixed> */
	private function to_client( ProtectedFile $f ): array {
		$activity = Plugin::instance()->protected_file_repository()->activity( $f->id );

		if ( $f->policy_id > 0 ) {
			$policy_label = $f->policy_name;
		} elseif ( $this->policy_is_restrictive( $f ) ) {
			$policy_label = __( 'Custom', 'rayetun-media-protection' );
		} else {
			$policy_label = __( 'Public link', 'rayetun-media-protection' );
		}

		return [
			'id'          => $f->id,
			'name'        => $f->original_filename,
			'mime'        => $f->mime_type,
			'size'        => $f->size_bytes,
			'policyId'    => $f->policy_id,
			'policyLabel' => $policy_label,
			'custom'      => AccessPolicyRecord::policy_to_client( $f->policy ),
			'activity'    => $activity,
		];
	}

	private function policy_is_restrictive( ProtectedFile $f ): bool {
		$p = $f->policy;
		return $p->require_login || ! empty( $p->allowed_roles ) || $p->max_clicks > 0 || '' !== $p->allowed_host;
	}
}
