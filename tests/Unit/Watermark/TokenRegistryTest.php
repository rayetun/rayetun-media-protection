<?php
/**
 * Unit tests for the dynamic token registry.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Watermark;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\TokenRegistry;

final class TokenRegistryTest extends TestCase {

	private function registry(): TokenRegistry {
		$registry = new TokenRegistry();
		$registry->register_defaults();
		return $registry;
	}

	public function test_resolves_user_email_from_context(): void {
		$context = new Context( user_email: 'jane@example.com' );
		$this->assertSame(
			'jane@example.com',
			$this->registry()->resolve( '{user_email}', $context )
		);
	}

	public function test_resolves_multiple_tokens_in_one_string(): void {
		$context = new Context( user_email: 'jane@example.com', order_id: 42 );
		$result  = $this->registry()->resolve( 'Licensed to {user_email} (order {order_id})', $context );
		$this->assertSame( 'Licensed to jane@example.com (order 42)', $result );
	}

	public function test_empty_context_uses_safe_defaults(): void {
		$registry = $this->registry();
		$context  = Context::empty();
		$this->assertSame( 'guest', $registry->resolve( '{user_email}', $context ) );
		$this->assertSame( '0', $registry->resolve( '{user_id}', $context ) );
		$this->assertSame( '0.0.0.0', $registry->resolve( '{user_ip}', $context ) );
		$this->assertSame( '0', $registry->resolve( '{order_id}', $context ) );
	}

	public function test_unknown_token_is_left_untouched(): void {
		$this->assertSame(
			'{not_a_real_token}',
			$this->registry()->resolve( '{not_a_real_token}', Context::empty() )
		);
	}

	public function test_text_without_braces_returned_verbatim(): void {
		$this->assertSame(
			'Plain copyright notice',
			$this->registry()->resolve( 'Plain copyright notice', Context::empty() )
		);
	}

	public function test_date_token_matches_iso_date(): void {
		$result = $this->registry()->resolve( '{date}', Context::empty() );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result );
	}

	public function test_custom_token_can_be_added(): void {
		$registry = new TokenRegistry();
		$registry->add( '{greeting}', static fn( Context $c ): string => 'hello' );
		$this->assertSame( 'hello world', $registry->resolve( '{greeting} world', Context::empty() ) );
	}

	public function test_tokens_list_reports_registered_placeholders(): void {
		$tokens = $this->registry()->tokens();
		$this->assertContains( '{user_email}', $tokens );
		$this->assertContains( '{download_id}', $tokens );
	}
}
