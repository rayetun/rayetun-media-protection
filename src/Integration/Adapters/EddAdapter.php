<?php
/**
 * Easy Digital Downloads integration.
 *
 * Hooks `edd_requested_file` — the filter EDD applies to the file path it is
 * about to serve. Buyer context (email, payment id) is read from the active
 * download arguments EDD exposes on the request.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration\Adapters;

use Rayetun\MarkGuard\Integration\Contracts\Integration;
use Rayetun\MarkGuard\Integration\PersonalizedFileService;
use Rayetun\MarkGuard\Watermark\Context;

defined( 'ABSPATH' ) || exit;

final class EddAdapter implements Integration {

	public function __construct(
		private readonly PersonalizedFileService $service
	) {}

	public function id(): string {
		return 'edd';
	}

	public function label(): string {
		return 'Easy Digital Downloads';
	}

	public function is_active(): bool {
		return class_exists( 'Easy_Digital_Downloads' );
	}

	public function register_hooks(): void {
		add_filter( 'edd_requested_file', [ $this, 'personalize_file' ], 20, 3 );
	}

	/**
	 * @param string $requested_file The file EDD is about to serve.
	 * @param array  $download_files All files for the download.
	 * @param string $file_key       Key of the requested file.
	 */
	public function personalize_file( $requested_file, $download_files = [], $file_key = '' ): string {
		$requested_file = (string) $requested_file;

		$email      = null;
		$payment_id = 0;

		// EDD exposes the current download request via its process args; read
		// the buyer email and payment id defensively.
		if ( function_exists( 'edd_get_payment_key' ) && isset( $_GET['eddfile'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- EDD's own token authenticates this request.
			$parts = explode( ':', sanitize_text_field( wp_unslash( $_GET['eddfile'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
			if ( isset( $parts[0] ) && function_exists( 'edd_get_payment' ) ) {
				$payment_id = (int) $parts[0];
				$payment    = edd_get_payment( $payment_id );
				if ( $payment && isset( $payment->email ) ) {
					$email = (string) $payment->email;
				}
			}
		}

		$context = new Context(
			user_id: get_current_user_id(),
			user_email: $email,
			order_id: $payment_id > 0 ? $payment_id : null
		);

		return $this->service->personalize( $requested_file, $context );
	}
}
