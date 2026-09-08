<?php
/**
 * Stateless signed token — an HMAC-SHA256 envelope around a small claims array.
 *
 * Kept pure (no WordPress calls) so it is fully unit-testable: the caller
 * supplies the signing secret. RayEtun Media Protection derives that secret from
 * wp_salt('auth') plus a per-install option, but this class does not care.
 *
 * Token format: mg1.<payload>.<signature>
 *   payload   = base64url( json_encode( claims ) )
 *   signature = base64url( hmac_sha256( "mg1." . payload, secret ) )
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

defined( 'ABSPATH' ) || exit;

final class SignedToken {

	private const PREFIX = 'mg1';

	/**
	 * Create a signed token from a claims array.
	 *
	 * @param array<string, scalar> $claims
	 */
	public static function create( array $claims, string $secret ): string {
		$payload = self::b64url_encode( (string) wp_json_encode( $claims ) );
		$signing = self::PREFIX . '.' . $payload;
		$sig     = self::b64url_encode( hash_hmac( 'sha256', $signing, $secret, true ) );
		return $signing . '.' . $sig;
	}

	/**
	 * Parse and verify a token. Returns the claims array when the signature is
	 * valid, or null when the token is malformed or tampered. Does NOT check
	 * expiry — callers inspect the returned claims for that.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function parse( string $token, string $secret ): ?array {
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) || self::PREFIX !== $parts[0] ) {
			return null;
		}
		[ $prefix, $payload, $sig ] = $parts;

		$expected = self::b64url_encode( hash_hmac( 'sha256', $prefix . '.' . $payload, $secret, true ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		$json = self::b64url_decode( $payload );
		if ( '' === $json ) {
			return null;
		}
		$claims = json_decode( $json, true );
		return is_array( $claims ) ? $claims : null;
	}

	private static function b64url_encode( string $data ): string {
		// base64url-encode this plugin's own signed token payload (not obfuscation).
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url transport encoding for a self-issued HMAC token.
	}

	private static function b64url_decode( string $data ): string {
		// Decodes this plugin's own base64url token before HMAC verification (not remote/hidden code).
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a self-issued token; integrity checked by HMAC in verify().
		return false === $decoded ? '' : $decoded;
	}
}
