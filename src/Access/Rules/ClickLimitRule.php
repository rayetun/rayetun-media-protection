<?php
/**
 * Deny requests once the link has been served its maximum number of times.
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

final class ClickLimitRule implements AccessRule {

	public function id(): string {
		return 'click-limit';
	}

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision {
		if ( 0 === $policy->max_clicks ) {
			return Decision::ABSTAIN;
		}
		return $request->clicks_used >= $policy->max_clicks ? Decision::DENY : Decision::ABSTAIN;
	}
}
