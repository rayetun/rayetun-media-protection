<?php
/**
 * Stores the watermark applied to e-commerce downloads: an on/off switch plus
 * the id of the watermark preset to stamp on each buyer's file.
 *
 * Disabled by default. A legacy inline rule (from before presets existed) is
 * still honored if present and no preset is selected.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Integration;

use Rayetun\MarkGuard\Watermark\PresetRepository;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

defined( 'ABSPATH' ) || exit;

final class WatermarkSettings {

	private const OPTION = 'markguard_ecommerce_watermark';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	public function is_enabled(): bool {
		return (bool) ( $this->data()['enabled'] ?? false );
	}

	public function preset_id(): int {
		return (int) ( $this->data()['presetId'] ?? 0 );
	}

	/**
	 * The rule to apply: the selected preset's rule, or a legacy inline rule.
	 */
	public function rule(): WatermarkRule {
		$preset_id = $this->preset_id();
		if ( $preset_id > 0 ) {
			$preset = ( new PresetRepository() )->find( $preset_id );
			if ( null !== $preset ) {
				return $preset->rule();
			}
		}
		$legacy = $this->data()['rule'] ?? [];
		return WatermarkRule::from_array( is_array( $legacy ) ? $legacy : [] );
	}

	/** True when watermarking is on but no usable preset is configured. */
	public function needs_preset(): bool {
		return $this->is_enabled() && 0 === $this->preset_id() && empty( $this->data()['rule'] );
	}

	/** @return array<string, mixed> */
	public function data(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, [] );
			$this->cache = is_array( $stored ) ? $stored : [];
		}
		return $this->cache;
	}

	public function save( bool $enabled, int $preset_id ): void {
		$data = $this->data();
		$data['enabled']  = $enabled;
		$data['presetId'] = max( 0, $preset_id );
		$this->cache      = $data;
		update_option( self::OPTION, $data, false );
	}

	public function set_enabled( bool $enabled ): void {
		$data            = $this->data();
		$data['enabled'] = $enabled;
		$this->cache     = $data;
		update_option( self::OPTION, $data, false );
	}
}
