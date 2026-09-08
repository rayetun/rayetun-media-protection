<?php
/**
 * Registry of RayEtun Media Protection's editor blocks. Drives the admin "Blocks" hub screen —
 * one card per entry — and is filterable so the pro plugin adds its blocks to
 * the same hub without any change here.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

defined( 'ABSPATH' ) || exit;

final class BlockRegistry {

	/**
	 * @return array<int, array{id:string, name:string, title:string, description:string, icon:string, defaultsKey:string, pro:bool}>
	 */
	public static function all(): array {
		$blocks = [
			[
				'id'          => 'protected-gallery',
				'name'        => 'markguard/protected-gallery',
				'title'       => __( 'Protected Gallery', 'rayetun-media-protection' ),
				'description' => __( 'Clean thumbnails, a watermarked lightbox preview, and an authorized clean-file download.', 'rayetun-media-protection' ),
				'icon'        => 'format-gallery',
				'defaultsKey' => 'gallery',
				'pro'         => false,
			],
			[
				'id'          => 'protected-download',
				'name'        => 'markguard/protected-download',
				'title'       => __( 'Protected Download', 'rayetun-media-protection' ),
				'description' => __( 'A signed, access-controlled download button for a single file.', 'rayetun-media-protection' ),
				'icon'        => 'download',
				'defaultsKey' => 'download',
				'pro'         => false,
			],
			[
				'id'          => 'protected-library',
				'name'        => 'markguard/protected-library',
				'title'       => __( 'Protected File Library', 'rayetun-media-protection' ),
				'description' => __( 'A grid of protected files with signed downloads and optional filtering.', 'rayetun-media-protection' ),
				'icon'        => 'portfolio',
				'defaultsKey' => 'library',
				'pro'         => false,
			],
		];

		/**
		 * Filters the registered RayEtun Media Protection blocks shown in the Blocks hub. Pro
		 * appends its blocks here.
		 *
		 * @param array<int, array<string, mixed>> $blocks
		 */
		return (array) apply_filters( 'markguard_blocks', $blocks );
	}
}
