<?php
/**
 * Dynamic watermark token registry.
 *
 * Resolves placeholders such as {user_email} or {order_id} against a Context
 * so watermarks can be personalized per download. Built-in tokens are
 * registered on construction; the `markguard/register_tokens` action lets pro
 * and third-party code add more.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

final class TokenRegistry {

	/** @var array<string, callable(Context):string> */
	private array $tokens = [];

	/**
	 * Register a token.
	 *
	 * @param string                   $token    Placeholder including braces, e.g. "{user_email}".
	 * @param callable(Context):string $resolver Returns the replacement string for a given context.
	 */
	public function add( string $token, callable $resolver ): void {
		$this->tokens[ $token ] = $resolver;
	}

	/**
	 * Replace every registered token in $text using $context. Unknown
	 * placeholders are left untouched so authors see their typo rather than an
	 * empty string.
	 */
	public function resolve( string $text, Context $context ): string {
		if ( '' === $text || ! str_contains( $text, '{' ) ) {
			return $text;
		}
		foreach ( $this->tokens as $token => $resolver ) {
			if ( str_contains( $text, $token ) ) {
				$text = str_replace( $token, (string) $resolver( $context ), $text );
			}
		}
		return $text;
	}

	/** @return string[] Registered token placeholders, for admin UI hints. */
	public function tokens(): array {
		return array_keys( $this->tokens );
	}

	/**
	 * Register the tokens that ship with RayEtun Media Protection core.
	 */
	public function register_defaults(): void {
		$this->add( '{user_email}', static fn( Context $c ): string => $c->user_email ?? 'guest' );
		$this->add( '{user_id}', static fn( Context $c ): string => (string) ( $c->user_id ?? 0 ) );
		$this->add( '{user_ip}', static fn( Context $c ): string => $c->request_ip ?? '0.0.0.0' );
		$this->add( '{order_id}', static fn( Context $c ): string => (string) ( $c->order_id ?? 0 ) );
		$this->add( '{date}', static fn( Context $c ): string => gmdate( 'Y-m-d' ) );
		$this->add( '{datetime}', static fn( Context $c ): string => gmdate( 'Y-m-d\TH:i:s\Z' ) );
		$this->add(
			'{site}',
			static function ( Context $c ): string {
				$host = function_exists( 'home_url' ) ? wp_parse_url( home_url(), PHP_URL_HOST ) : null;
				return is_string( $host ) ? $host : 'site';
			}
		);
		$this->add( '{download_id}', static fn( Context $c ): string => $c->download_signature ?? '' );
	}
}
