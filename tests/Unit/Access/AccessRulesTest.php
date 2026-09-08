<?php
/**
 * Unit tests for individual access rules.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Access;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessRequest;
use Rayetun\MarkGuard\Access\Decision;
use Rayetun\MarkGuard\Access\Rules\ClickLimitRule;
use Rayetun\MarkGuard\Access\Rules\ExpirationRule;
use Rayetun\MarkGuard\Access\Rules\HotlinkRule;
use Rayetun\MarkGuard\Access\Rules\LoggedInRule;
use Rayetun\MarkGuard\Access\Rules\RoleRule;

final class AccessRulesTest extends TestCase {

	public function test_logged_in_rule(): void {
		$rule      = new LoggedInRule();
		$anonymous = new AccessRequest( user_id: 0 );
		$member    = new AccessRequest( user_id: 5 );

		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( new AccessPolicy( require_login: false ), $anonymous ) );
		$this->assertSame( Decision::DENY, $rule->evaluate( new AccessPolicy( require_login: true ), $anonymous ) );
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( new AccessPolicy( require_login: true ), $member ) );
	}

	public function test_role_rule(): void {
		$rule       = new RoleRule();
		$subscriber = new AccessRequest( user_id: 1, user_roles: [ 'subscriber' ] );
		$customer   = new AccessRequest( user_id: 2, user_roles: [ 'customer' ] );

		$policy = new AccessPolicy( allowed_roles: [ 'customer', 'administrator' ] );

		$this->assertSame( Decision::DENY, $rule->evaluate( $policy, $subscriber ) );
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( $policy, $customer ) );
		// No role restriction → abstain regardless.
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( new AccessPolicy(), $subscriber ) );
	}

	public function test_expiration_rule(): void {
		$request = new AccessRequest();

		$not_expired = new ExpirationRule( now: 1000 );
		$this->assertSame( Decision::ABSTAIN, $not_expired->evaluate( new AccessPolicy( expires_at: 2000 ), $request ) );

		$expired = new ExpirationRule( now: 3000 );
		$this->assertSame( Decision::DENY, $expired->evaluate( new AccessPolicy( expires_at: 2000 ), $request ) );

		// No expiry set → abstain.
		$this->assertSame( Decision::ABSTAIN, $expired->evaluate( new AccessPolicy( expires_at: 0 ), $request ) );
	}

	public function test_click_limit_rule(): void {
		$rule = new ClickLimitRule();

		$this->assertSame(
			Decision::ABSTAIN,
			$rule->evaluate( new AccessPolicy( max_clicks: 3 ), new AccessRequest( clicks_used: 2 ) )
		);
		$this->assertSame(
			Decision::DENY,
			$rule->evaluate( new AccessPolicy( max_clicks: 3 ), new AccessRequest( clicks_used: 3 ) )
		);
		$this->assertSame(
			Decision::ABSTAIN,
			$rule->evaluate( new AccessPolicy( max_clicks: 0 ), new AccessRequest( clicks_used: 99 ) )
		);
	}

	public function test_hotlink_rule(): void {
		$rule   = new HotlinkRule();
		$policy = new AccessPolicy( allowed_host: 'example.com' );

		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( $policy, new AccessRequest( referrer_host: 'example.com' ) ) );
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( $policy, new AccessRequest( referrer_host: 'EXAMPLE.COM' ) ) );
		$this->assertSame( Decision::DENY, $rule->evaluate( $policy, new AccessRequest( referrer_host: 'evil.com' ) ) );
		// No referrer → allow.
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( $policy, new AccessRequest( referrer_host: '' ) ) );
		// No host restriction → abstain.
		$this->assertSame( Decision::ABSTAIN, $rule->evaluate( new AccessPolicy(), new AccessRequest( referrer_host: 'evil.com' ) ) );
	}
}
