<?php
/**
 * Access rule contract. Rules are pure predicates over a policy + request,
 * returning a Decision. Registered via `markguard_register_access_rules` so
 * pro/third-party code can add more (geo-IP, membership tier, etc.).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access\Contracts;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessRequest;
use Rayetun\MarkGuard\Access\Decision;

defined( 'ABSPATH' ) || exit;

interface AccessRule {

	/** Machine id, for logging which rule fired. */
	public function id(): string;

	public function evaluate( AccessPolicy $policy, AccessRequest $request ): Decision;
}
