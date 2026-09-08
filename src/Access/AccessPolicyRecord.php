<?php
/**
 * A named, reusable access policy.
 *
 * Governs WHO may download (login / roles), HOW MANY times a link may be used
 * (max_clicks), and hotlink host. Link EXPIRY is chosen per-link when a link is
 * minted, not stored here — so a policy maps directly onto the runtime
 * AccessPolicy with expires_at left at 0.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

final class AccessPolicyRecord {

	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly AccessPolicy $policy
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'id'   => $this->id,
			'name' => $this->name,
			'rule' => self::policy_to_client( $this->policy ),
		];
	}

	/** @return array<string, mixed> */
	public static function policy_to_client( AccessPolicy $p ): array {
		return [
			'requireLogin' => $p->require_login,
			'allowedRoles' => $p->allowed_roles,
			'maxClicks'    => $p->max_clicks,
			'allowedHost'  => $p->allowed_host,
		];
	}

	/**
	 * @param array<string, mixed> $client
	 */
	public static function policy_from_client( array $client ): AccessPolicy {
		$roles = $client['allowedRoles'] ?? [];
		return new AccessPolicy(
			require_login: (bool) ( $client['requireLogin'] ?? false ),
			allowed_roles: is_array( $roles ) ? array_values( array_map( 'sanitize_key', $roles ) ) : [],
			expires_at: 0,
			max_clicks: max( 0, (int) ( $client['maxClicks'] ?? 0 ) ),
			allowed_host: sanitize_text_field( (string) ( $client['allowedHost'] ?? '' ) )
		);
	}
}
