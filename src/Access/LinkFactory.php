<?php
/**
 * Builds signed download URLs for protected files.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

use Rayetun\MarkGuard\Storage\ProtectedFile;
use Rayetun\MarkGuard\Support\SignedToken;
use Rayetun\MarkGuard\Support\Signing;

defined( 'ABSPATH' ) || exit;

final class LinkFactory {

	public const QUERY_VAR = 'markguard_download';

	/**
	 * Create a signed download URL. The token carries the file id, a unique
	 * per-link nonce (used for click counting), and an expiry — derived from
	 * the file's policy unless an explicit $ttl_seconds overrides it.
	 */
	public function create( ProtectedFile $file, ?int $ttl_seconds = null ): string {
		$nonce = wp_generate_password( 16, false, false );

		$expires_at = $file->policy->expires_at;
		if ( null !== $ttl_seconds && $ttl_seconds > 0 ) {
			$expires_at = time() + $ttl_seconds;
		}

		$claims = [
			'fid' => $file->id,
			'n'   => $nonce,
			'exp' => $expires_at,
		];

		$token = SignedToken::create( $claims, Signing::secret() );

		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}
}
