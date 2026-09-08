<?php
/**
 * Clean-copy downloads — the "watermarked preview, clean original for authorized
 * users" (stock-photo / proofing) model.
 *
 * When auto-watermark runs, the image shown on the site is the watermarked one.
 * With this feature on, the *clean* original is also registered as a protected
 * file (reusing RayEtun Media Protection's storage + signed-link + access-policy system) and
 * linked to the attachment via post meta. The `[markguard_clean_download]`
 * shortcode then offers that clean original to visitors who satisfy the policy.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Media;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessPolicyResolver;
use Rayetun\MarkGuard\Access\LinkFactory;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Storage\ProtectedFile;
use Rayetun\MarkGuard\Support\Filesystem;

defined( 'ABSPATH' ) || exit;

final class CleanCopyService {

	public const OPTION   = 'markguard_clean_downloads';
	public const META_KEY = '_markguard_clean_file_id';

	public function is_enabled(): bool {
		$option = get_option( self::OPTION, [] );
		return is_array( $option ) && ! empty( $option['enabled'] );
	}

	public function policy_id(): int {
		$option = get_option( self::OPTION, [] );
		return is_array( $option ) ? max( -1, (int) ( $option['policyId'] ?? 0 ) ) : 0;
	}

	/**
	 * Register the clean original as a protected file and remember it on the
	 * attachment. Must be called while $clean_file is still un-watermarked.
	 * Returns the protected-file id, or 0 on failure / when already registered.
	 *
	 * @param int|null $policy_override -1 Everyone / 0 Logged-in / >0 named; when
	 *                                  null, the clean-downloads option is used.
	 */
	public function register( int $attachment_id, string $clean_file, string $mime, ?int $policy_override = null ): int {
		$repo     = Plugin::instance()->protected_file_repository();
		$existing = (int) get_post_meta( $attachment_id, self::META_KEY, true );
		if ( $existing > 0 && null !== $repo->find( $existing ) ) {
			return $existing;
		}
		if ( ! is_readable( $clean_file ) ) {
			return 0;
		}

		// The storage backend MOVES its source, so hand it a throwaway copy —
		// never the live media file (which still needs to be watermarked).
		// wp_tempnam() lives in an admin include that is absent on the front end
		// (where the gallery block renders), so load it on demand.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( basename( $clean_file ) );
		if ( '' === $tmp || ! Filesystem::copy( $clean_file, $tmp ) ) {
			return 0;
		}

		$backend     = Plugin::instance()->storage_backend( 'local' );
		$logical_key = bin2hex( random_bytes( 16 ) );
		$storage     = $backend->store( $tmp, $logical_key );
		if ( null === $storage ) {
			Filesystem::delete( $tmp );
			return 0;
		}

		$policy_id = null !== $policy_override ? $policy_override : $this->policy_id();
		$id        = $repo->insert(
			$attachment_id,
			$logical_key,
			$storage,
			basename( $clean_file ),
			$backend->id(),
			$mime,
			(int) ( @filesize( $storage ) ?: 0 ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort size.
			AccessPolicyResolver::from_id( $policy_id ),
			max( 0, $policy_id ) // rule_set_id is an unsigned column.
		);

		if ( $id > 0 ) {
			update_post_meta( $attachment_id, self::META_KEY, $id );
		}
		return $id;
	}

	/**
	 * Bring an existing clean copy's policy in line with the requested id, only
	 * writing when it actually changed (this runs on front-end renders).
	 */
	public function sync_policy( ProtectedFile $file, int $policy_id ): void {
		$repo    = Plugin::instance()->protected_file_repository();
		$desired = AccessPolicyResolver::from_id( $policy_id );
		$same    = $file->policy_id === max( 0, $policy_id ) && $file->policy->to_array() === $desired->to_array();
		if ( $same ) {
			return;
		}
		if ( $policy_id > 0 ) {
			$repo->assign_named_policy( $file->id, $policy_id );
		} else {
			$repo->update_policy( $file->id, $desired );
		}
	}

	public function file_for_attachment( int $attachment_id ): ?ProtectedFile {
		$id = (int) get_post_meta( $attachment_id, self::META_KEY, true );
		if ( $id <= 0 ) {
			return null;
		}
		return Plugin::instance()->protected_file_repository()->find( $id );
	}

	public function link( ProtectedFile $file ): string {
		return ( new LinkFactory() )->create( $file );
	}

	/**
	 * A lightweight identity check for whether to show the download button. The
	 * full policy (expiry, click limit, hotlink) is still enforced by the serve
	 * handler when the link is actually used; this only governs button display.
	 */
	public function viewer_allowed( AccessPolicy $policy ): bool {
		if ( $policy->require_login && ! is_user_logged_in() ) {
			return false;
		}
		if ( ! empty( $policy->allowed_roles ) ) {
			$user = wp_get_current_user();
			if ( ! $user || empty( array_intersect( $policy->allowed_roles, (array) $user->roles ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Remove the clean copy (row + stored bytes + meta) when its attachment is
	 * deleted. Hooked on `delete_attachment`.
	 */
	public function purge_for_attachment( int $attachment_id ): void {
		$id = (int) get_post_meta( $attachment_id, self::META_KEY, true );
		if ( $id > 0 ) {
			$repo = Plugin::instance()->protected_file_repository();
			$file = $repo->find( $id );
			if ( null !== $file ) {
				Plugin::instance()->storage_backend( $file->backend )->delete( $file->storage_path );
				$repo->delete( $id );
			}
		}
		delete_post_meta( $attachment_id, self::META_KEY );
	}
}
