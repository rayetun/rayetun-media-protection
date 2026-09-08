<?php
/**
 * Unit tests for the rule evaluator (deny-overrides semantics).
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Access;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessRequest;
use Rayetun\MarkGuard\Access\RuleEvaluator;
use Rayetun\MarkGuard\Access\Rules\ClickLimitRule;
use Rayetun\MarkGuard\Access\Rules\ExpirationRule;
use Rayetun\MarkGuard\Access\Rules\HotlinkRule;
use Rayetun\MarkGuard\Access\Rules\LoggedInRule;
use Rayetun\MarkGuard\Access\Rules\RoleRule;

final class RuleEvaluatorTest extends TestCase {

	private function evaluator(): RuleEvaluator {
		return new RuleEvaluator(
			[
				new LoggedInRule(),
				new RoleRule(),
				new ExpirationRule( now: 1000 ),
				new ClickLimitRule(),
				new HotlinkRule(),
			]
		);
	}

	public function test_allows_when_no_rule_denies(): void {
		$policy  = new AccessPolicy( require_login: true, expires_at: 5000, max_clicks: 10 );
		$request = new AccessRequest( user_id: 3, clicks_used: 1 );

		$this->assertTrue( $this->evaluator()->allows( $policy, $request ) );
		$this->assertNull( $this->evaluator()->first_denial( $policy, $request ) );
	}

	public function test_denies_and_reports_login_rule(): void {
		$policy  = new AccessPolicy( require_login: true );
		$request = new AccessRequest( user_id: 0 );

		$this->assertFalse( $this->evaluator()->allows( $policy, $request ) );
		$this->assertSame( 'logged-in', $this->evaluator()->first_denial( $policy, $request ) );
	}

	public function test_denies_on_expiry_even_when_logged_in(): void {
		$policy  = new AccessPolicy( expires_at: 500 ); // now=1000 → expired.
		$request = new AccessRequest( user_id: 9 );

		$this->assertSame( 'expiration', $this->evaluator()->first_denial( $policy, $request ) );
	}

	public function test_first_denial_wins_in_order(): void {
		// Both login and click-limit would deny; login is registered first.
		$policy  = new AccessPolicy( require_login: true, max_clicks: 1 );
		$request = new AccessRequest( user_id: 0, clicks_used: 5 );

		$this->assertSame( 'logged-in', $this->evaluator()->first_denial( $policy, $request ) );
	}

	public function test_empty_policy_allows_everything(): void {
		$this->assertTrue( $this->evaluator()->allows( new AccessPolicy(), new AccessRequest() ) );
	}
}
