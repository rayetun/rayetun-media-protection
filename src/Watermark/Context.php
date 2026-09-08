<?php
/**
 * Watermark context — the surrounding facts about who and why.
 *
 * Kept intentionally small in v1.0.0; only carries data actually consumed by
 * the token resolver. Grows as pro/integration features add more sources.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Watermark;

defined( 'ABSPATH' ) || exit;

final class Context {

	public function __construct(
		public readonly ?int $user_id = null,
		public readonly ?string $user_email = null,
		public readonly ?string $request_ip = null,
		public readonly ?int $order_id = null,
		public readonly ?int $attachment_id = null,
		public readonly ?string $download_signature = null
	) {}

	public static function empty(): self {
		return new self();
	}
}
