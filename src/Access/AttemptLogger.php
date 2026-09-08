<?php
/**
 * Writes access-attempt rows to the log table. IP and user-agent are stored as
 * salted hashes, never in the clear.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Access;

use Rayetun\MarkGuard\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class AttemptLogger {

	public function log(
		int $file_id,
		string $link_nonce,
		string $event,
		string $reason,
		AccessRequest $request,
		string $user_agent = ''
	): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::log_table(),
			[
				'file_id'    => $file_id,
				'link_nonce' => $link_nonce,
				'event'      => $event,
				'reason'     => $reason,
				'user_id'    => $request->user_id,
				'ip_hash'    => $this->hash( $request->ip ),
				'ua_hash'    => $this->hash( $user_agent ),
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Salted SHA-256 so entries cannot be correlated across sites and no raw
	 * PII is retained.
	 */
	private function hash( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		return hash_hmac( 'sha256', $value, wp_salt( 'nonce' ) );
	}
}
