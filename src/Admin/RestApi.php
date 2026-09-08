<?php
/**
 * Registers all RayEtun Media Protection REST controllers on rest_api_init.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Admin;

use Rayetun\MarkGuard\Admin\Rest\AbstractController;
use Rayetun\MarkGuard\Admin\Rest\AnalyticsController;
use Rayetun\MarkGuard\Admin\Rest\BlocksController;
use Rayetun\MarkGuard\Admin\Rest\DashboardController;
use Rayetun\MarkGuard\Admin\Rest\GalleryController;
use Rayetun\MarkGuard\Admin\Rest\FilesController;
use Rayetun\MarkGuard\Admin\Rest\IntegrationsController;
use Rayetun\MarkGuard\Admin\Rest\PoliciesController;
use Rayetun\MarkGuard\Admin\Rest\PresetsController;
use Rayetun\MarkGuard\Admin\Rest\SettingsController;

defined( 'ABSPATH' ) || exit;

final class RestApi {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( $this->controllers() as $controller ) {
			$controller->register_routes();
		}
	}

	/** @return AbstractController[] */
	private function controllers(): array {
		$controllers = [
			new DashboardController(),
			new SettingsController(),
			new PresetsController(),
			new PoliciesController(),
			new FilesController(),
			new IntegrationsController(),
			new AnalyticsController(),
			new GalleryController(),
			new BlocksController(),
		];

		/**
		 * Filters the REST controllers RayEtun Media Protection registers.
		 *
		 * @param AbstractController[] $controllers
		 */
		return apply_filters( 'markguard_rest_controllers', $controllers );
	}
}
