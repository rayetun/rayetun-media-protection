<?php
/**
 * Shared helper for the Protected Download and File Library blocks: register a
 * chosen attachment as a protected file with a given access policy, and produce
 * the per-viewer render data (signed link when allowed, locked otherwise).
 *
 * The block stores the protected-file id in its own attributes, so the policy
 * lives on that file and is edited per block — independent of the gallery's
 * clean copies (which are keyed by post meta).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

use Rayetun\MarkGuard\Access\AccessPolicyResolver;
use Rayetun\MarkGuard\Access\LinkFactory;
use Rayetun\MarkGuard\Media\CleanCopyService;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Support\Filesystem;

defined( 'ABSPATH' ) || exit;

final class ProtectedDownloadService {

	/**
	 * Ensure a protected copy of $attachment_id exists with $policy_id, reusing
	 * $existing_id when it already points at this attachment (just updating the
	 * policy). Returns display info for the editor.
	 *
	 * @return array{fileId:int, name:string, size:int, mime:string, ok:bool}
	 */
	public function resolve( int $attachment_id, int $policy_id, int $existing_id = 0 ): array {
		$repo = Plugin::instance()->protected_file_repository();
		$file = $existing_id > 0 ? $repo->find( $existing_id ) : null;

		if ( null !== $file && $file->attachment_id !== $attachment_id ) {
			// The block now points at a different file — drop the block's copy.
			Plugin::instance()->storage_backend( $file->backend )->delete( $file->storage_path );
			$repo->delete( $file->id );
			$file = null;
		}

		// New pick: prefer a fresh copy from the live media file. If that file is
		// gone (e.g. "Auto-protect uploads" moved a non-image into protected
		// storage on upload), reuse the attachment's existing protected file.
		if ( null === $file ) {
			$src = get_attached_file( $attachment_id );
			if ( is_string( $src ) && is_readable( $src ) ) {
				return $this->info( $this->register( $attachment_id, $policy_id, $src ) );
			}
			$file = $repo->find_by_attachment( $attachment_id );
		}

		if ( null === $file ) {
			return $this->info( 0 );
		}

		$this->set_policy( $file->id, $policy_id );
		return $this->info( $file->id );
	}

	private function set_policy( int $file_id, int $policy_id ): void {
		$repo = Plugin::instance()->protected_file_repository();
		if ( $policy_id > 0 ) {
			$repo->assign_named_policy( $file_id, $policy_id );
		} else {
			$repo->update_policy( $file_id, AccessPolicyResolver::from_id( $policy_id ) );
		}
	}

	private function register( int $attachment_id, int $policy_id, string $src ): int {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( basename( $src ) );
		if ( '' === $tmp || ! Filesystem::copy( $src, $tmp ) ) {
			return 0;
		}
		$backend = Plugin::instance()->storage_backend( 'local' );
		$key     = bin2hex( random_bytes( 16 ) );
		$storage = $backend->store( $tmp, $key );
		if ( null === $storage ) {
			Filesystem::delete( $tmp );
			return 0;
		}
		return Plugin::instance()->protected_file_repository()->insert(
			$attachment_id,
			$key,
			$storage,
			basename( $src ),
			$backend->id(),
			(string) get_post_mime_type( $attachment_id ),
			(int) ( @filesize( $storage ) ?: 0 ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort size.
			AccessPolicyResolver::from_id( $policy_id ),
			// rule_set_id is an unsigned column — only a real named-policy id.
			max( 0, $policy_id )
		);
	}

	/** @return array{fileId:int, name:string, size:int, mime:string, ok:bool} */
	private function info( int $file_id ): array {
		$file = $file_id > 0 ? Plugin::instance()->protected_file_repository()->find( $file_id ) : null;
		if ( null === $file ) {
			return [ 'fileId' => 0, 'name' => '', 'size' => 0, 'mime' => '', 'ok' => false ];
		}
		return [
			'fileId' => $file->id,
			'name'   => $file->original_filename,
			'size'   => $file->size_bytes,
			'mime'   => $file->mime_type,
			'ok'     => true,
		];
	}

	/**
	 * Per-viewer render data for a protected file.
	 *
	 * @return array{name:string, size:int, mime:string, downloadable:bool, downloadUrl:string}|null
	 */
	public function render_data( int $file_id ): ?array {
		$file = $file_id > 0 ? Plugin::instance()->protected_file_repository()->find( $file_id ) : null;
		if ( null === $file ) {
			return null;
		}
		$allowed = ( new CleanCopyService() )->viewer_allowed( $file->policy );
		return [
			'name'         => $file->original_filename,
			'size'         => $file->size_bytes,
			'mime'         => $file->mime_type,
			'downloadable' => $allowed,
			'downloadUrl'  => $allowed ? ( new LinkFactory() )->create( $file ) : '',
		];
	}
}
