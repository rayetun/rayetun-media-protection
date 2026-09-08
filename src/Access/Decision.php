<?php
/**
 * Outcome of evaluating a single access rule.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

defined( 'ABSPATH' ) || exit;

enum Decision {
	case ALLOW;   // Explicit grant (reserved for future whitelist-style rules).
	case DENY;    // Hard block — first DENY wins.
	case ABSTAIN; // No opinion — defer to other rules / default policy.
}
