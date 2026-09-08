<?php
/**
 * Storage backend contract. Registered via `markguard_register_storage_backends`
 * so pro/third-party code can add S3, R2, etc.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Storage\Contracts;

defined( 'ABSPATH' ) || exit;

interface StorageBackend {

	public function id(): string;

	public function label(): string;

	/**
	 * Move a local file into protected storage under $logical_key.
	 * Returns the absolute storage path on success, or null on failure.
	 */
	public function store( string $source_path, string $logical_key ): ?string;

	/**
	 * Stream the stored file to the browser with the given download filename
	 * and MIME type. Sends headers and outputs bytes; caller exits afterward.
	 */
	public function stream( string $storage_path, string $download_filename, string $mime_type ): void;

	public function delete( string $storage_path ): bool;

	public function exists( string $storage_path ): bool;
}
