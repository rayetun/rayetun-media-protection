<?php
/**
 * Media Library integration: a "Protected" status column and per-item
 * Protect / Unprotect actions.
 *
 * Protect moves the file into protected storage; Unprotect moves it back to its
 * original library location. Both are guarded by capability + nonce and run via
 * admin-post (no JavaScript required).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Media;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Support\Capabilities;
use Rayetun\MarkGuard\Support\Filesystem;

defined( 'ABSPATH' ) || exit;

final class MediaActions {

	private const PROTECT_ACTION   = 'markguard_protect_attachment';
	private const UNPROTECT_ACTION = 'markguard_unprotect_attachment';

	public function register(): void {
		add_filter( 'media_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_filter( 'manage_media_columns', [ $this, 'add_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'admin_post_' . self::PROTECT_ACTION, [ $this, 'handle_protect' ] );
		add_action( 'admin_post_' . self::UNPROTECT_ACTION, [ $this, 'handle_unprotect' ] );
	}

	/**
	 * @param array<string, string> $actions
	 * @param \WP_Post               $post
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, $post ): array {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return $actions;
		}
		$repo      = Plugin::instance()->protected_file_repository();
		$protected = null !== $repo->find_by_attachment( (int) $post->ID );

		if ( $protected ) {
			$actions['markguard_unprotect'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( self::UNPROTECT_ACTION, (int) $post->ID ) ),
				esc_html__( 'Unprotect', 'rayetun-media-protection' )
			);
		} else {
			$actions['markguard_protect'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( self::PROTECT_ACTION, (int) $post->ID ) ),
				esc_html__( 'Protect with RayEtun Media Protection', 'rayetun-media-protection' )
			);
		}
		return $actions;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$columns['rayetun-media-protection'] = __( 'RayEtun Media Protection', 'rayetun-media-protection' );
		return $columns;
	}

	public function render_column( string $column, int $attachment_id ): void {
		if ( 'rayetun-media-protection' !== $column ) {
			return;
		}
		$protected = null !== Plugin::instance()->protected_file_repository()->find_by_attachment( $attachment_id );
		if ( $protected ) {
			echo '<span style="color:#059669;font-weight:600;">' . esc_html__( 'Protected', 'rayetun-media-protection' ) . '</span>';
		} else {
			echo '<span style="color:#94a3b8;">' . esc_html__( '—', 'rayetun-media-protection' ) . '</span>';
		}
	}

	public function handle_protect(): void {
		$attachment_id = $this->validate_request( self::PROTECT_ACTION );

		$file = get_attached_file( $attachment_id );
		if ( is_string( $file ) && is_readable( $file ) ) {
			$plugin  = Plugin::instance();
			$repo    = $plugin->protected_file_repository();
			if ( null === $repo->find_by_attachment( $attachment_id ) ) {
				$backend = $plugin->storage_backend( 'local' );
				$key     = bin2hex( random_bytes( 16 ) );
				$storage = $backend->store( $file, $key );
				if ( null !== $storage ) {
					$repo->insert(
						$attachment_id,
						$key,
						$storage,
						basename( $file ),
						$backend->id(),
						(string) ( get_post_mime_type( $attachment_id ) ?: 'application/octet-stream' ),
						(int) ( @filesize( $storage ) ?: 0 ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort size.
						new AccessPolicy(),
						0
					);
				}
			}
		}

		$this->redirect_back();
	}

	public function handle_unprotect(): void {
		$attachment_id = $this->validate_request( self::UNPROTECT_ACTION );

		$plugin = Plugin::instance();
		$repo   = $plugin->protected_file_repository();
		$file   = $repo->find_by_attachment( $attachment_id );
		if ( null !== $file ) {
			$destination = get_attached_file( $attachment_id );
			if ( is_string( $destination ) && '' !== $destination ) {
				Filesystem::move( $file->storage_path, $destination );
			} else {
				$plugin->storage_backend( $file->backend )->delete( $file->storage_path );
			}
			$repo->delete( $file->id );
		}

		$this->redirect_back();
	}

	private function validate_request( string $action ): int {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'rayetun-media-protection' ), 403 );
		}
		$attachment_id = isset( $_GET['attachment'] ) ? absint( wp_unslash( $_GET['attachment'] ) ) : 0;
		check_admin_referer( $action . '_' . $attachment_id );
		return $attachment_id;
	}

	private function action_url( string $action, int $attachment_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&attachment=' . $attachment_id ),
			$action . '_' . $attachment_id
		);
	}

	private function redirect_back(): void {
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'upload.php' ) );
		exit;
	}
}
