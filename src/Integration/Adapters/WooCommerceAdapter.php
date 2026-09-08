<?php
/**
 * WooCommerce integration.
 *
 * Hooks `woocommerce_download_product_filepath` — the filter WooCommerce
 * applies to the file path just before serving a downloadable product. The
 * filter carries the buyer's email and order, which we turn into a Context and
 * use to stamp a personalized watermark onto the served copy.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration\Adapters;

use Rayetun\MarkGuard\Integration\Contracts\Integration;
use Rayetun\MarkGuard\Integration\PersonalizedFileService;
use Rayetun\MarkGuard\Watermark\Context;

defined( 'ABSPATH' ) || exit;

final class WooCommerceAdapter implements Integration {

	public function __construct(
		private readonly PersonalizedFileService $service
	) {}

	public function id(): string {
		return 'woocommerce';
	}

	public function label(): string {
		return 'WooCommerce';
	}

	public function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function register_hooks(): void {
		add_filter( 'woocommerce_download_product_filepath', [ $this, 'personalize_path' ], 20, 5 );
	}

	/**
	 * @param string $file_path     The resolved download path/URL.
	 * @param string $email_address Buyer email.
	 * @param mixed  $order         WC_Order|false.
	 * @param mixed  $product       WC_Product.
	 * @param mixed  $download      WC_Customer_Download.
	 */
	public function personalize_path( $file_path, $email_address, $order = false, $product = null, $download = null ): string {
		$file_path = (string) $file_path;

		$order_id = 0;
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		}

		$context = new Context(
			user_id: get_current_user_id(),
			user_email: is_string( $email_address ) && '' !== $email_address ? $email_address : null,
			request_ip: null,
			order_id: $order_id > 0 ? $order_id : null
		);

		return $this->service->personalize( $file_path, $context );
	}
}
