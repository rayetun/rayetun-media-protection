<?php
/**
 * Base REST controller — shared namespace and permission checks.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin\Rest;

use Rayetun\MarkGuard\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

abstract class AbstractController {

	protected const NAMESPACE = 'markguard/v1';

	abstract public function register_routes(): void;

	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	public function can_view_analytics(): bool {
		return current_user_can( Capabilities::ANALYTICS ) || current_user_can( Capabilities::MANAGE );
	}
}
