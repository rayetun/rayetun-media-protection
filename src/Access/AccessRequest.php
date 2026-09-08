<?php
/**
 * Immutable snapshot of an incoming request for a protected file.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

final class AccessRequest {

	/**
	 * @param int      $user_id       0 for anonymous.
	 * @param string[] $user_roles    Role slugs of the current user.
	 * @param string   $ip            Requesting IP (raw; hashed before storage).
	 * @param string   $referrer_host Host portion of the HTTP referrer, or ''.
	 * @param int      $clicks_used   How many times this link has already been served.
	 */
	public function __construct(
		public readonly int $user_id = 0,
		public readonly array $user_roles = [],
		public readonly string $ip = '',
		public readonly string $referrer_host = '',
		public readonly int $clicks_used = 0
	) {}

	public function is_logged_in(): bool {
		return $this->user_id > 0;
	}

	public function has_role( string $role ): bool {
		return in_array( $role, $this->user_roles, true );
	}
}
