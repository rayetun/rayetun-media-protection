<?php
/**
 * Deny requests after the policy's expiry timestamp.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access\Rules;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessRequest;
use Rayetun\MarkGuard\Access\Contracts\AccessRule;
use Rayetun\MarkGuard\Access\Decision;

defined( 'ABSPATH' ) || exit;

final class ExpirationRule implements AccessRule {

	public function __construct(
		private readonly ?int $now = null
	) {}

	public function id(): string {
		return 'expiration';
	}

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision {
		if ( 0 === $policy->expires_at ) {
			return Decision::ABSTAIN;
		}
		$now = $this->now ?? time();
		return $now > $policy->expires_at ? Decision::DENY : Decision::ABSTAIN;
	}
}
