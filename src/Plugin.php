<?php
/**
 * RayEtun Media Protection main plugin class.
 *
 * Owns the service container, boots subsystems (watermark, storage, access,
 * integrations, admin), and provides activation, deactivation, and uninstall
 * entry points.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard;

defined( 'ABSPATH' ) || exit;

use Rayetun\MarkGuard\Access\AttemptLogger;
use Rayetun\MarkGuard\Access\RuleEvaluator;
use Rayetun\MarkGuard\Admin\Menu;
use Rayetun\MarkGuard\Admin\RestApi;
use Rayetun\MarkGuard\Access\Rules\ClickLimitRule;
use Rayetun\MarkGuard\Access\Rules\ExpirationRule;
use Rayetun\MarkGuard\Access\Rules\HotlinkRule;
use Rayetun\MarkGuard\Access\Rules\LoggedInRule;
use Rayetun\MarkGuard\Access\Rules\RoleRule;
use Rayetun\MarkGuard\Database\Schema;
use Rayetun\MarkGuard\Database\Upgrader;
use Rayetun\MarkGuard\Deterrent\DeterrentLayer;
use Rayetun\MarkGuard\Blocks\ProtectedDownloadBlock;
use Rayetun\MarkGuard\Blocks\ProtectedLibraryBlock;
use Rayetun\MarkGuard\Frontend\CleanDownloadShortcode;
use Rayetun\MarkGuard\Gallery\GalleryBlock;
use Rayetun\MarkGuard\Integration\AdapterRegistry;
use Rayetun\MarkGuard\Integration\Adapters\DownloadMonitorAdapter;
use Rayetun\MarkGuard\Integration\Adapters\EddAdapter;
use Rayetun\MarkGuard\Integration\Adapters\WooCommerceAdapter;
use Rayetun\MarkGuard\Integration\PersonalizedFileService;
use Rayetun\MarkGuard\Integration\WatermarkSettings;
use Rayetun\MarkGuard\Media\MediaActions;
use Rayetun\MarkGuard\Media\UploadProtector;
use Rayetun\MarkGuard\Storage\Backends\LocalBackend;
use Rayetun\MarkGuard\Storage\Contracts\StorageBackend;
use Rayetun\MarkGuard\Storage\Handler;
use Rayetun\MarkGuard\Storage\ProtectedFileRepository;
use Rayetun\MarkGuard\Support\Capabilities;
use Rayetun\MarkGuard\Watermark\Engines\ImageEngine;
use Rayetun\MarkGuard\Watermark\Engines\PdfEngine;
use Rayetun\MarkGuard\Watermark\Registry as WatermarkRegistry;
use Rayetun\MarkGuard\Watermark\TokenRegistry;

final class Plugin {

	private static ?self $instance = null;

	private bool $booted = false;

	private WatermarkRegistry $watermark_registry;

	private TokenRegistry $token_registry;

	private ProtectedFileRepository $file_repository;

	/** @var array<string, StorageBackend> */
	private array $storage_backends = [];

	private ?Handler $handler = null;

	private WatermarkSettings $watermark_settings;

	private AdapterRegistry $adapter_registry;

	private ?PersonalizedFileService $personalized_file_service = null;

	public const GC_CRON_HOOK = 'markguard_gc_temp_files';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->watermark_registry = new WatermarkRegistry();
		$this->token_registry     = new TokenRegistry();
		$this->file_repository    = new ProtectedFileRepository();
		$this->watermark_settings = new WatermarkSettings();
		$this->adapter_registry   = new AdapterRegistry();
	}

	public function watermark_settings(): WatermarkSettings {
		return $this->watermark_settings;
	}

	public function adapter_registry(): AdapterRegistry {
		return $this->adapter_registry;
	}

	public function personalized_file_service(): PersonalizedFileService {
		if ( null === $this->personalized_file_service ) {
			$this->personalized_file_service = new PersonalizedFileService(
				$this->watermark_registry,
				$this->watermark_settings
			);
		}
		return $this->personalized_file_service;
	}

	public function watermark_registry(): WatermarkRegistry {
		return $this->watermark_registry;
	}

	public function token_registry(): TokenRegistry {
		return $this->token_registry;
	}

	public function protected_file_repository(): ProtectedFileRepository {
		return $this->file_repository;
	}

	public function storage_backend( string $id ): StorageBackend {
		return $this->storage_backends[ $id ] ?? $this->storage_backends['local'];
	}

	public function download_handler(): Handler {
		if ( null === $this->handler ) {
			$this->handler = new Handler(
				$this->file_repository,
				new RuleEvaluator( $this->access_rules() ),
				new AttemptLogger(),
				$this->storage_backends
			);
		}
		return $this->handler;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		// Note: load_plugin_textdomain() is not called — WordPress.org auto-loads
		// translations for plugins hosted in the directory since WP 4.6.

		Upgrader::maybe_upgrade();
		Capabilities::ensure();

		$this->register_tokens();
		$this->register_watermark_engines();
		$this->register_storage_backends();
		$this->download_handler()->register();
		$this->register_integrations();
		$this->register_media();
		$this->register_deterrents();
		$this->register_clean_downloads();
		$this->register_gallery();
		$this->register_gc_cron();
		$this->register_admin();

		$this->booted = true;
	}

	private function register_media(): void {
		( new UploadProtector() )->register();
		// Registered unconditionally: the row-action/column filters only fire on
		// admin list screens, and the admin-post handlers must be available on
		// admin-post.php requests (where is_admin() is not reliably true).
		( new MediaActions() )->register();
	}

	private function register_admin(): void {
		( new RestApi() )->register();
		if ( is_admin() ) {
			( new Menu() )->register();
		}
	}

	private function register_deterrents(): void {
		( new DeterrentLayer() )->register();
	}

	private function register_clean_downloads(): void {
		( new CleanDownloadShortcode() )->register();
		// Clean up the protected clean-copy when its attachment is deleted —
		// registered regardless of the feature flag so cleanup still runs if the
		// feature was later turned off.
		add_action(
			'delete_attachment',
			static function ( int $attachment_id ): void {
				( new \Rayetun\MarkGuard\Media\CleanCopyService() )->purge_for_attachment( $attachment_id );
			}
		);
	}

	private function register_gallery(): void {
		( new GalleryBlock() )->register();
		( new ProtectedDownloadBlock() )->register();
		( new ProtectedLibraryBlock() )->register();

		add_action(
			'delete_attachment',
			static function ( int $attachment_id ): void {
				// Cached gallery derivatives (clean thumbnail + watermarked preview).
				( new \Rayetun\MarkGuard\Gallery\GalleryAssetService() )->purge( $attachment_id );
				// Every protected copy tied to this attachment (clean copy +
				// per-block download copies) and its stored bytes.
				$repo = self::instance()->protected_file_repository();
				foreach ( $repo->all_by_attachment( $attachment_id ) as $file ) {
					self::instance()->storage_backend( $file->backend )->delete( $file->storage_path );
					$repo->delete( $file->id );
				}
			}
		);
	}

	private function register_integrations(): void {
		$service = $this->personalized_file_service();

		$this->adapter_registry->register( new WooCommerceAdapter( $service ) );
		$this->adapter_registry->register( new EddAdapter( $service ) );
		$this->adapter_registry->register( new DownloadMonitorAdapter( $service ) );

		/**
		 * Fires so pro/third-party code can register additional integrations.
		 *
		 * @param AdapterRegistry            $registry
		 * @param PersonalizedFileService    $service
		 */
		do_action( 'markguard_register_integrations', $this->adapter_registry, $service );

		$this->adapter_registry->boot_active();
	}

	private function register_gc_cron(): void {
		add_action( self::GC_CRON_HOOK, [ PersonalizedFileService::class, 'gc' ] );
		add_action( self::GC_CRON_HOOK, [ \Rayetun\MarkGuard\Database\LogPruner::class, 'prune' ] );
		if ( ! wp_next_scheduled( self::GC_CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::GC_CRON_HOOK );
		}
	}

	private function register_tokens(): void {
		$this->token_registry->register_defaults();

		/**
		 * Fires when RayEtun Media Protection is ready to accept additional watermark tokens.
		 *
		 * @param TokenRegistry $registry
		 */
		do_action( 'markguard_register_tokens', $this->token_registry );
	}

	private function register_watermark_engines(): void {
		$this->watermark_registry->register( 'image', new ImageEngine( $this->token_registry ) );
		$this->watermark_registry->register( 'pdf', new PdfEngine( $this->token_registry ) );

		/**
		 * Fires when RayEtun Media Protection is ready to accept additional watermark engines.
		 *
		 * @param WatermarkRegistry $registry
		 */
		do_action( 'markguard_register_watermark_engines', $this->watermark_registry );
	}

	private function register_storage_backends(): void {
		$this->storage_backends['local'] = new LocalBackend();

		/**
		 * Filters the registered storage backends (id => StorageBackend).
		 *
		 * @param array<string, StorageBackend> $backends
		 */
		$this->storage_backends = apply_filters( 'markguard_register_storage_backends', $this->storage_backends );
	}

	/**
	 * The ordered access-rule chain evaluated for every download.
	 *
	 * @return \Rayetun\MarkGuard\Access\Contracts\AccessRule[]
	 */
	private function access_rules(): array {
		$rules = [
			new LoggedInRule(),
			new RoleRule(),
			new ExpirationRule(),
			new ClickLimitRule(),
			new HotlinkRule(),
		];

		/**
		 * Filters the access-rule chain (pro adds geo-IP, membership, etc.).
		 *
		 * @param \Rayetun\MarkGuard\Access\Contracts\AccessRule[] $rules
		 */
		return apply_filters( 'markguard_register_access_rules', $rules );
	}

	public static function activate(): void {
		Schema::install();
		Capabilities::ensure();
		LocalBackend::protected_root(); // Create + guard the protected directory.
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::GC_CRON_HOOK );
		// Keep data intact; uninstall handles removal.
	}
}
