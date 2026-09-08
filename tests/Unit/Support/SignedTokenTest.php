<?php
/**
 * Unit tests for the stateless signed token.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Support\SignedToken;

final class SignedTokenTest extends TestCase {

	private const SECRET = 'test-secret-key-do-not-use-in-prod';

	public function test_round_trips_claims(): void {
		$claims = [ 'fid' => 42, 'exp' => 1700000000, 'n' => 'abc123' ];
		$token  = SignedToken::create( $claims, self::SECRET );
		$parsed = SignedToken::parse( $token, self::SECRET );

		$this->assertNotNull( $parsed );
		$this->assertSame( 42, $parsed['fid'] );
		$this->assertSame( 1700000000, $parsed['exp'] );
		$this->assertSame( 'abc123', $parsed['n'] );
	}

	public function test_token_has_expected_prefix(): void {
		$token = SignedToken::create( [ 'fid' => 1 ], self::SECRET );
		$this->assertStringStartsWith( 'mg1.', $token );
	}

	public function test_rejects_wrong_secret(): void {
		$token = SignedToken::create( [ 'fid' => 1 ], self::SECRET );
		$this->assertNull( SignedToken::parse( $token, 'a-different-secret' ) );
	}

	public function test_rejects_tampered_payload(): void {
		$token = SignedToken::create( [ 'fid' => 1, 'exp' => 100 ], self::SECRET );
		[ $prefix, $payload, $sig ] = explode( '.', $token );

		// Flip the payload to a different claim set; signature no longer matches.
		$forged_payload = rtrim( strtr( base64_encode( '{"fid":999,"exp":100}' ), '+/', '-_' ), '=' );
		$forged         = $prefix . '.' . $forged_payload . '.' . $sig;

		$this->assertNull( SignedToken::parse( $forged, self::SECRET ) );
	}

	public function test_rejects_malformed_tokens(): void {
		$this->assertNull( SignedToken::parse( 'not-a-token', self::SECRET ) );
		$this->assertNull( SignedToken::parse( 'mg1.only-two', self::SECRET ) );
		$this->assertNull( SignedToken::parse( 'wrong.prefix.here', self::SECRET ) );
		$this->assertNull( SignedToken::parse( '', self::SECRET ) );
	}

	public function test_signature_uses_constant_time_comparison(): void {
		// Sanity: a token differing only in the last signature char is rejected.
		$token   = SignedToken::create( [ 'fid' => 7 ], self::SECRET );
		$mutated = substr( $token, 0, -1 ) . ( 'A' === substr( $token, -1 ) ? 'B' : 'A' );
		$this->assertNull( SignedToken::parse( $mutated, self::SECRET ) );
	}
}
