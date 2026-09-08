<?php
/**
 * Produces and caches the three representations a protected gallery needs for
 * one attachment:
 *
 *  - a CLEAN thumbnail (small, public) for the grid,
 *  - a WATERMARKED preview (large, public) for the lightbox,
 *  - a CLEAN original in protected storage for authorized download.
 *
 * The clean full-resolution file is never emitted publicly: the grid thumbnail
 * is small and low-value, the lightbox image carries the watermark, and the
 * clean original is served only through the signed, access-controlled handler.
 * So even a browser extension that defeats the right-click deterrent captures
 * only the watermarked preview.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Gallery;

use Rayetun\MarkGuard\Media\CleanCopyService;
use Rayetun\MarkGuard\Media\UploadProtector;
use Rayetun\MarkGuard\Plugin;
use Rayetun\MarkGuard\Support\Filesystem;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\PresetRepository;

defined( 'ABSPATH' ) || exit;

final class GalleryAssetService {

	private const PREVIEWS_DIR = 'markguard-previews';

	public const DEFAULT_THUMB_MAX   = 500;
	public const DEFAULT_PREVIEW_MAX = 1400;

	public static function previews_root(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::PREVIEWS_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$index = trailingslashit( $dir ) . 'index.php';
			if ( ! file_exists( $index ) ) {
				Filesystem::put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}
		return $dir;
	}

	public static function previews_url(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . self::PREVIEWS_DIR;
	}

	/**
	 * Ensure derivatives + a downloadable clean copy exist, generating whatever
	 * is missing. A missing/failed watermarked preview yields ok:false — we
	 * never fall back to serving a clean large image.
	 *
	 * @return array{ok:bool, thumbUrl:string, previewUrl:string, title:string, width:int, height:int, downloadable:bool}
	 */
	public function ensure(
		int $attachment_id,
		int $preset_id,
		int $thumb_max = self::DEFAULT_THUMB_MAX,
		int $preview_max = self::DEFAULT_PREVIEW_MAX,
		int $policy_id = 0
	): array {
		$fail = [ 'ok' => false, 'thumbUrl' => '', 'previewUrl' => '', 'title' => '', 'width' => 0, 'height' => 0, 'downloadable' => false ];

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return $fail;
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( ! str_starts_with( $mime, 'image/' ) ) {
			return $fail;
		}

		$clean = $this->clean_source( $attachment_id );
		if ( null === $clean ) {
			return $fail;
		}

		// A downloadable clean copy — created regardless of upload-time flags,
		// with the gallery's chosen access policy.
		$downloadable = $this->ensure_clean_copy( $attachment_id, $clean, $mime, $policy_id ) > 0;

		$ext        = $this->extension_for( $clean );
		$thumb_file = trailingslashit( self::previews_root() ) . "{$attachment_id}-t{$thumb_max}.{$ext}";
		$sig        = $this->preview_signature( $preset_id, $preview_max );
		$prev_file  = trailingslashit( self::previews_root() ) . "{$attachment_id}-p{$sig}.{$ext}";

		if ( ! file_exists( $thumb_file ) && ! $this->make_resized( $clean, $thumb_file, $thumb_max ) ) {
			return $fail;
		}
		if ( ! file_exists( $prev_file ) && ! $this->make_watermarked_preview( $clean, $prev_file, $preview_max, $preset_id, $mime ) ) {
			return $fail;
		}

		$dims = wp_getimagesize( $prev_file );

		return [
			'ok'           => true,
			'thumbUrl'     => trailingslashit( self::previews_url() ) . basename( $thumb_file ),
			'previewUrl'   => trailingslashit( self::previews_url() ) . basename( $prev_file ),
			'title'        => get_the_title( $attachment_id ),
			'width'        => is_array( $dims ) ? (int) $dims[0] : 0,
			'height'       => is_array( $dims ) ? (int) $dims[1] : 0,
			'downloadable' => $downloadable,
		];
	}

	/**
	 * Resolve the clean original: the auto-watermark backup when present (the
	 * public media file is watermarked in that case), otherwise the attachment
	 * file itself.
	 */
	private function clean_source( int $attachment_id ): ?string {
		$backup_dir = UploadProtector::backups_dir();
		foreach ( [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ] as $e ) {
			$path = trailingslashit( $backup_dir ) . $attachment_id . '.' . $e;
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		$file = get_attached_file( $attachment_id );
		return ( is_string( $file ) && is_readable( $file ) ) ? $file : null;
	}

	private function ensure_clean_copy( int $attachment_id, string $clean, string $mime, int $policy_id ): int {
		$service  = new CleanCopyService();
		$existing = $service->file_for_attachment( $attachment_id );
		if ( null !== $existing ) {
			$service->sync_policy( $existing, $policy_id );
			return $existing->id;
		}
		return $service->register( $attachment_id, $clean, $mime, $policy_id );
	}

	private function extension_for( string $path ): string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ], true ) ? $ext : 'jpg';
	}

	private function preview_signature( int $preset_id, int $preview_max ): string {
		$preset = $preset_id > 0 ? ( new PresetRepository() )->find( $preset_id ) : ( new PresetRepository() )->default_upload_preset();
		$config = null !== $preset ? $preset->config : [];
		return substr( md5( (string) wp_json_encode( [ $preset_id, $config, $preview_max ] ) ), 0, 10 );
	}

	private function make_resized( string $source, string $dest, int $max ): bool {
		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->resize( $max, $max, false );
		$saved = $editor->save( $dest );
		return ! is_wp_error( $saved ) && file_exists( $dest );
	}

	/**
	 * Resize a clean copy, then watermark it in place. Requires a preset; with
	 * none we delete the file and fail, so a clean large image is never served.
	 */
	private function make_watermarked_preview( string $source, string $dest, int $max, int $preset_id, string $mime ): bool {
		if ( ! $this->make_resized( $source, $dest, $max ) ) {
			return false;
		}
		$preset = $preset_id > 0 ? ( new PresetRepository() )->find( $preset_id ) : ( new PresetRepository() )->default_upload_preset();
		$engine = Plugin::instance()->watermark_registry()->resolve_for_mime( $mime );
		if ( null === $preset || null === $engine ) {
			Filesystem::delete( $dest );
			return false;
		}
		$result = $engine->apply( $dest, $preset->rule(), Context::empty() );
		if ( ! $result->success && ! $result->skipped ) {
			Filesystem::delete( $dest );
			return false;
		}
		return true;
	}

	/** Remove every cached derivative for an attachment (hooked on delete). */
	public function purge( int $attachment_id ): void {
		$pattern = trailingslashit( self::previews_root() ) . $attachment_id . '-*';
		foreach ( (array) glob( $pattern ) as $file ) {
			if ( is_string( $file ) ) {
				Filesystem::delete( $file );
			}
		}
	}
}
