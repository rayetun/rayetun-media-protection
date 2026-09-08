<?php
/**
 * The access configuration attached to a protected file: which restrictions
 * apply. Stored per-file as JSON; hydrated into this value object.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

final class AccessPolicy {

	/**
	 * @param bool     $require_login  Require the requester to be logged in.
	 * @param string[] $allowed_roles  If non-empty, requester must have one of these roles.
	 * @param int      $expires_at     Unix timestamp; 0 means no time limit.
	 * @param int      $max_clicks     Maximum serves; 0 means unlimited.
	 * @param string   $allowed_host   Hotlink guard: only this referrer host may embed; '' disables.
	 */
	public function __construct(
		public readonly bool $require_login = false,
		public readonly array $allowed_roles = [],
		public readonly int $expires_at = 0,
		public readonly int $max_clicks = 0,
		public readonly string $allowed_host = ''
	) {}

	/** @param array<string, mixed> $data */
	public static function from_array( array $data ): self {
		$roles = $data['allowed_roles'] ?? [];
		return new self(
			require_login: (bool) ( $data['require_login'] ?? false ),
			allowed_roles: is_array( $roles ) ? array_values( array_map( 'strval', $roles ) ) : [],
			expires_at: max( 0, (int) ( $data['expires_at'] ?? 0 ) ),
			max_clicks: max( 0, (int) ( $data['max_clicks'] ?? 0 ) ),
			allowed_host: (string) ( $data['allowed_host'] ?? '' ),
		);
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'require_login' => $this->require_login,
			'allowed_roles' => $this->allowed_roles,
			'expires_at'    => $this->expires_at,
			'max_clicks'    => $this->max_clicks,
			'allowed_host'  => $this->allowed_host,
		];
	}
}
