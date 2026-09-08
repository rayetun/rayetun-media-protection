<?php
/**
 * Deny requests whose referrer host does not match the policy's allowed host.
 * Requests with no referrer are allowed (direct navigation, privacy tools).
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

final class HotlinkRule implements AccessRule {

	public function id(): string {
		return 'hotlink';
	}

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision {
		if ( '' === $policy->allowed_host ) {
			return Decision::ABSTAIN;
		}
		// No referrer → allow (browsers strip it for direct hits and privacy).
		if ( '' === $request->referrer_host ) {
			return Decision::ABSTAIN;
		}
		return strcasecmp( $request->referrer_host, $policy->allowed_host ) === 0
			? Decision::ABSTAIN
			: Decision::DENY;
	}
}
