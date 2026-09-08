<?php
/**
 * A stored, protected file record.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Storage;

use Rayetun\MarkGuard\Access\AccessPolicy;

defined( 'ABSPATH' ) || exit;

final class ProtectedFile {

	public function __construct(
		public readonly int $id,
		public readonly int $attachment_id,
		public readonly string $logical_key,
		public readonly string $storage_path,
		public readonly string $original_filename,
		public readonly string $backend,
		public readonly string $mime_type,
		public readonly int $size_bytes,
		public readonly AccessPolicy $policy,
		public readonly int $policy_id = 0,
		public readonly string $policy_name = ''
	) {}
}
