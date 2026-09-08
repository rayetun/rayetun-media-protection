<?php
/**
 * Applies the auto-watermark and auto-protect rules to new media uploads.
 *
 * On `add_attachment`:
 *  - Auto-watermark: images and PDFs get the default upload preset applied
 *    in place. The original is copied to a backups directory first, so the
 *    watermark is reversible.
 *  - Auto-protect: non-image downloadables (PDF, ZIP, …) are moved into
 *    protected storage with the configured default policy. Images are left in
 *    the library (they are meant to be watermarked, not hidden).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Media;

use Rayetun\MarkGuard\Access\AccessPolicy;
use Rayetun\MarkGuard\Access\AccessPolicyRepository;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Storage\Backends\LocalBackend;
use Rayetun\MarkGuard\Support\FeatureFlags;
use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\PresetRepository;

defined( 'ABSPATH' ) || exit;

final class UploadProtector {

	public function register(): void {
		// Priority 20: after core has stored the file, before thumbnail meta
		// generation (which runs on a later, separate call for images).
		add_action( 'add_attachment', [ $this, 'on_add_attachment' ], 20 );
	}

	public function on_add_attachment( int $attachment_id ): void {
		$flags = ( new FeatureFlags() )->all();
		if ( empty( $flags[ FeatureFlags::AUTO_WATERMARK ] ) && empty( $flags[ FeatureFlags::AUTO_PROTECT ] ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return;
		}
		$mime     = (string) get_post_mime_type( $attachment_id );
		$is_image = str_starts_with( $mime, 'image/' );
		$is_pdf   = 'application/pdf' === $mime;

		if ( ! empty( $flags[ FeatureFlags::AUTO_WATERMARK ] ) && ( $is_image || $is_pdf ) ) {
			$this->auto_watermark( $attachment_id, $file, $mime );
		}

		if ( ! empty( $flags[ FeatureFlags::AUTO_PROTECT ] ) && ! $is_image ) {
			$this->auto_protect( $attachment_id, $file, $mime );
		}
	}

	private function auto_watermark( int $attachment_id, string $file, string $mime ): void {
		$preset = ( new PresetRepository() )->default_upload_preset();
		if ( null === $preset ) {
			return;
		}
		$engine = Plugin::instance()->watermark_registry()->resolve_for_mime( $mime );
		if ( null === $engine ) {
			return;
		}

		// Keep a restorable original before watermarking in place.
		$this->backup_original( $attachment_id, $file );

		// Optionally register the still-clean original as a protected file so
		// authorized visitors can download it via [markguard_clean_download].
		// Must happen before the in-place watermark below.
		$clean = new CleanCopyService();
		if ( $clean->is_enabled() ) {
			$clean->register( $attachment_id, $file, $mime );
		}

		$engine->apply( $file, $preset->rule(), Context::empty() );
	}

	private function auto_protect( int $attachment_id, string $file, string $mime ): void {
		$repo = Plugin::instance()->protected_file_repository();
		if ( null !== $repo->find_by_attachment( $attachment_id ) ) {
			return; // Already protected.
		}

		$backend     = Plugin::instance()->storage_backend( 'local' );
		$logical_key = bin2hex( random_bytes( 16 ) );
		$storage     = $backend->store( $file, $logical_key );
		if ( null === $storage ) {
			return;
		}

		$policy_id = (int) ( get_option( 'markguard_auto_protect', [] )['policyId'] ?? 0 );
		$policy    = $this->policy_for( $policy_id );

		$repo->insert(
			$attachment_id,
			$logical_key,
			$storage,
			basename( $file ),
			$backend->id(),
			$mime,
			(int) ( @filesize( $storage ) ?: 0 ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort size.
			$policy,
			$policy_id
		);
	}

	private function policy_for( int $policy_id ): AccessPolicy {
		if ( $policy_id > 0 ) {
			$record = ( new AccessPolicyRepository() )->find( $policy_id );
			if ( null !== $record ) {
				return $record->policy;
			}
		}
		return new AccessPolicy();
	}

	public static function backups_dir(): string {
		$dir = trailingslashit( LocalBackend::protected_root() ) . 'originals';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	private function backup_original( int $attachment_id, string $file ): void {
		$ext    = pathinfo( $file, PATHINFO_EXTENSION );
		$backup = trailingslashit( self::backups_dir() ) . $attachment_id . ( '' !== $ext ? '.' . $ext : '' );
		if ( ! file_exists( $backup ) ) {
			Filesystem::copy( $file, $backup );
		}
	}
}
