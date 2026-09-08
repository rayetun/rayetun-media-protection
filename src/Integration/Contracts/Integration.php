<?php
/**
 * Integration contract. Each adapter bridges one e-commerce/download plugin to
 * RayEtun Media Protection's personalization pipeline. Registered via
 * `markguard_register_integrations` so pro/third-party code can add more.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration\Contracts;

defined( 'ABSPATH' ) || exit;

interface Integration {

	/** Machine id (e.g. 'woocommerce'). */
	public function id(): string;

	/** Human label for admin UI. */
	public function label(): string;

	/** True when the target plugin is installed and active. */
	public function is_active(): bool;

	/** Attach the plugin-specific hooks. Only called when is_active() is true. */
	public function register_hooks(): void;
}
