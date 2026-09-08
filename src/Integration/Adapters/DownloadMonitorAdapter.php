<?php
/**
 * Download Monitor integration.
 *
 * Hooks `dlm_downloading_file_path`, the filter Download Monitor applies to the
 * local path of the file version it is about to stream. Download Monitor has no
 * inherent buyer identity, so the Context is built from the current logged-in
 * user (email/id) when available.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration\Adapters;

use Rayetun\MarkGuard\Integration\Contracts\Integration;
use Rayetun\MarkGuard\Integration\PersonalizedFileService;
use Rayetun\MarkGuard\Watermark\Context;

defined( 'ABSPATH' ) || exit;

final class DownloadMonitorAdapter implements Integration {

	public function __construct(
		private readonly PersonalizedFileService $service
	) {}

	public function id(): string {
		return 'download-monitor';
	}

	public function label(): string {
		return 'Download Monitor';
	}

	public function is_active(): bool {
		return class_exists( 'WP_DLM' ) || function_exists( 'download_monitor' );
	}

	public function register_hooks(): void {
		add_filter( 'dlm_downloading_file_path', [ $this, 'personalize_path' ], 20, 1 );
	}

	/**
	 * @param string $file_path Local path of the file version being served.
	 */
	public function personalize_path( $file_path ): string {
		$file_path = (string) $file_path;

		$email   = null;
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$user = wp_get_current_user();
			if ( $user instanceof \WP_User && '' !== $user->user_email ) {
				$email = $user->user_email;
			}
		}

		$context = new Context(
			user_id: $user_id,
			user_email: $email
		);

		return $this->service->personalize( $file_path, $context );
	}
}
