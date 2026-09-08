<?php
/**
 * Deny requests whose user does not hold one of the policy's allowed roles.
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

final class RoleRule implements AccessRule {

	public function id(): string {
		return 'role';
	}

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision {
		if ( empty( $policy->allowed_roles ) ) {
			return Decision::ABSTAIN;
		}
		foreach ( $policy->allowed_roles as $role ) {
			if ( $request->has_role( $role ) ) {
				return Decision::ABSTAIN;
			}
		}
		return Decision::DENY;
	}
}
