<?php
/**
 * Access policies REST controller — CRUD for reusable named policies, plus the
 * list of assignable WordPress roles.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Access\AccessPolicyRecord;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class PoliciesController extends AbstractController {

	private AccessPolicyRepository $repository;

	public function __construct() {
		$this->repository = new AccessPolicyRepository();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/access-policies',
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
			'/access-policies/(?P<id>\d+)',
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
	}

	public function index(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'policies' => array_map(
					static fn( AccessPolicyRecord $r ): array => $r->to_array(),
					$this->repository->all()
				),
				'roles'    => $this->roles(),
			],
			200
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$name   = $this->clean_name( (string) $request->get_param( 'name' ) );
		$policy = AccessPolicyRecord::policy_from_client( (array) $request->get_param( 'rule' ) );
		$id     = $this->repository->create( $name, $policy );
		if ( 0 === $id ) {
			return new WP_REST_Response( [ 'message' => __( 'Could not save the policy.', 'rayetun-media-protection' ) ], 500 );
		}
		return new WP_REST_Response( $this->repository->find( $id )->to_array(), 201 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( null === $this->repository->find( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Policy not found.', 'rayetun-media-protection' ) ], 404 );
		}
		$name   = $this->clean_name( (string) $request->get_param( 'name' ) );
		$policy = AccessPolicyRecord::policy_from_client( (array) $request->get_param( 'rule' ) );
		$this->repository->update( $id, $name, $policy );
		return new WP_REST_Response( $this->repository->find( $id )->to_array(), 200 );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$this->repository->delete( (int) $request->get_param( 'id' ) );
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/** @return array<string, string> role slug => display name */
	private function roles(): array {
		$out = [];
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$out[ $slug ] = translate_user_role( $name );
		}
		return $out;
	}

	private function clean_name( string $name ): string {
		$name = sanitize_text_field( $name );
		return '' !== $name ? $name : __( 'Untitled policy', 'rayetun-media-protection' );
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
		];
	}
}
