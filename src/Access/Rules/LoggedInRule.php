<?php
/**
 * Deny anonymous requests when the policy requires login.
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

final class LoggedInRule implements AccessRule {

	public function id(): string {
		return 'logged-in';
	}

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision {
		if ( ! $policy->require_login ) {
			return Decision::ABSTAIN;
		}
		return $request->is_logged_in() ? Decision::ABSTAIN : Decision::DENY;
	}
}
