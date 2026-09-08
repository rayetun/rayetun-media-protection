<?php
/**
 * Evaluates a set of access rules against a policy + request.
 *
 * Semantics: first DENY wins (deny-overrides). If no rule denies, access is
 * granted — the signed token has already established the request is authentic,
 * and rules only *restrict* from there.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

use Rayetun\MarkGuard\Access\Contracts\AccessRule;

defined( 'ABSPATH' ) || exit;

final class RuleEvaluator {

	/** @var AccessRule[] */
	private array $rules;

	/** @param AccessRule[] $rules */
	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Returns null when access is granted, or the id of the first rule that
	 * denied (useful for logging the reason).
	 */
	public function first_denial( AccessPolicy $policy, AccessRequest $request ): ?string {
		foreach ( $this->rules as $rule ) {
			if ( Decision::DENY === $rule->evaluate( $policy, $request ) ) {
				return $rule->id();
			}
		}
		return null;
	}

	public function allows( AccessPolicy $policy, AccessRequest $request ): bool {
		return null === $this->first_denial( $policy, $request );
	}
}
