<?php
/**
 * Maps a block's numeric "who can download" selection to an AccessPolicy:
 *
 *   -1  → Everyone (public, no restriction)
 *    0  → Logged-in users (the safe default)
 *   >0  → a named policy (falls back to logged-in if it was deleted)
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

final class AccessPolicyResolver {

	public const EVERYONE = -1;
	public const LOGGED_IN = 0;

	public static function from_id( int $id ): AccessPolicy {
		if ( $id > 0 ) {
			$record = ( new AccessPolicyRepository() )->find( $id );
			return null !== $record ? $record->policy : new AccessPolicy( require_login: true );
		}
		if ( self::EVERYONE === $id ) {
			return new AccessPolicy();
		}
		return new AccessPolicy( require_login: true );
	}
}
