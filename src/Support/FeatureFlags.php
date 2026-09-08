<?php
/**
 * Read/write the four dashboard feature toggles. Each maps to an option group
 * that later milestones extend (auto-watermark/protect config, deterrent
 * sub-toggles); here we own the master "enabled" switch.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Support;

use Rayetun\MarkGuard\Integration\WatermarkSettings;

defined( 'ABSPATH' ) || exit;

final class FeatureFlags {

	public const AUTO_WATERMARK = 'autoWatermark';
	public const AUTO_PROTECT   = 'autoProtect';
	public const DETERRENTS     = 'deterrents';
	public const ECOMMERCE      = 'ecommerce';

	private const OPTION_MAP = [
		self::AUTO_WATERMARK => 'markguard_auto_watermark',
		self::AUTO_PROTECT   => 'markguard_auto_protect',
		self::DETERRENTS     => 'markguard_deterrents',
	];

	/** @return array<string, bool> */
	public function all(): array {
		return [
			self::AUTO_WATERMARK => $this->flag( self::OPTION_MAP[ self::AUTO_WATERMARK ] ),
			self::AUTO_PROTECT   => $this->flag( self::OPTION_MAP[ self::AUTO_PROTECT ] ),
			self::DETERRENTS     => $this->flag( self::OPTION_MAP[ self::DETERRENTS ] ),
			self::ECOMMERCE      => ( new WatermarkSettings() )->is_enabled(),
		];
	}

	public static function keys(): array {
		return [ self::AUTO_WATERMARK, self::AUTO_PROTECT, self::DETERRENTS, self::ECOMMERCE ];
	}

	public function set( string $key, bool $enabled ): bool {
		if ( self::ECOMMERCE === $key ) {
			( new WatermarkSettings() )->set_enabled( $enabled );
			return true;
		}
		if ( ! isset( self::OPTION_MAP[ $key ] ) ) {
			return false;
		}
		$option = self::OPTION_MAP[ $key ];
		$data   = get_option( $option, [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}
		$data['enabled'] = $enabled;
		update_option( $option, $data, false );
		return true;
	}

	private function flag( string $option ): bool {
		$data = get_option( $option, [] );
		return is_array( $data ) && ! empty( $data['enabled'] );
	}
}
