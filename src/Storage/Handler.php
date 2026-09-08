<?php
/**
 * Serve handler for signed download links.
 *
 * Listens on the front end for `?markguard_download=<token>`, verifies the
 * token signature and expiry, evaluates the file's access policy, logs the
 * attempt, and streams the file — or denies with a 403/404.
 *
 * Authentication for this public endpoint is the HMAC-signed token itself, not
 * a WordPress nonce; the token is unguessable and integrity-protected. Reads
 * of $_GET here are therefore intentionally not nonce-verified.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Storage;

use Rayetun\MarkGuard\Access\AttemptLogger;
use Rayetun\MarkGuard\Access\LinkFactory;
use Rayetun\MarkGuard\Access\RequestFactory;
use Rayetun\MarkGuard\Access\RuleEvaluator;
use Rayetun\MarkGuard\Storage\Contracts\StorageBackend;
use Rayetun\MarkGuard\Support\SignedToken;
use Rayetun\MarkGuard\Support\Signing;

defined( 'ABSPATH' ) || exit;

final class Handler {

	/** @param array<string, StorageBackend> $backends */
	public function __construct(
		private readonly ProtectedFileRepository $repository,
		private readonly RuleEvaluator $evaluator,
		private readonly AttemptLogger $logger,
		private readonly array $backends
	) {}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'maybe_handle' ], 0 );
	}

	public function maybe_handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public download endpoint; auth is the HMAC-signed token, not a WP nonce.
		if ( empty( $_GET[ LinkFactory::QUERY_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See class docblock: token integrity is verified by HMAC in check().
		$token = sanitize_text_field( wp_unslash( $_GET[ LinkFactory::QUERY_VAR ] ) );

		$this->serve( $token );
	}

	/**
	 * Evaluate a token WITHOUT side effects (no logging, no streaming). Used by
	 * both serve() and the CLI verify command.
	 *
	 * @return array{outcome:string, file_id:int, nonce:string, reason:string, file:?ProtectedFile}
	 */
	public function check( string $token ): array {
		$claims = SignedToken::parse( $token, Signing::secret() );
		if ( null === $claims || ! isset( $claims['fid'], $claims['n'] ) ) {
			return $this->result( 'invalid' );
		}

		$file_id = (int) $claims['fid'];
		$nonce   = (string) $claims['n'];
		$exp     = (int) ( $claims['exp'] ?? 0 );

		if ( $exp > 0 && time() > $exp ) {
			return $this->result( 'expired', $file_id, $nonce, 'token-exp' );
		}

		$file = $this->repository->find( $file_id );
		if ( null === $file ) {
			return $this->result( 'not_found', $file_id, $nonce );
		}

		$clicks_used = $this->repository->count_serves( $file_id, $nonce );
		$request     = RequestFactory::from_globals( $clicks_used );

		$denial = $this->evaluator->first_denial( $file->policy, $request );
		if ( null !== $denial ) {
			return $this->result( 'denied', $file_id, $nonce, $denial, $file );
		}

		return $this->result( 'allowed', $file_id, $nonce, '', $file );
	}

	public function serve( string $token ): void {
		$check   = $this->check( $token );
		$request = RequestFactory::from_globals();
		$ua      = RequestFactory::user_agent();

		switch ( $check['outcome'] ) {
			case 'invalid':
				$this->deny_now( 403, __( 'This download link is invalid.', 'rayetun-media-protection' ) );
				return;

			case 'expired':
				$this->logger->log( $check['file_id'], $check['nonce'], 'expired', 'token-exp', $request, $ua );
				$this->deny_now( 403, __( 'This download link has expired.', 'rayetun-media-protection' ) );
				return;

			case 'not_found':
				$this->deny_now( 404, __( 'The requested file was not found.', 'rayetun-media-protection' ) );
				return;

			case 'denied':
				$this->logger->log( $check['file_id'], $check['nonce'], 'denied', $check['reason'], $request, $ua );
				$this->deny_now( 403, __( 'You do not have access to this file.', 'rayetun-media-protection' ) );
				return;
		}

		$file    = $check['file'];
		$backend = $file ? ( $this->backends[ $file->backend ] ?? null ) : null;
		if ( ! $file instanceof ProtectedFile || ! $backend instanceof StorageBackend || ! $backend->exists( $file->storage_path ) ) {
			$this->deny_now( 404, __( 'The requested file is no longer available.', 'rayetun-media-protection' ) );
			return;
		}

		$this->logger->log( $file->id, $check['nonce'], 'served', '', $request, $ua );

		$backend->stream( $file->storage_path, $file->original_filename, $file->mime_type );
		exit;
	}

	/**
	 * @return array{outcome:string, file_id:int, nonce:string, reason:string, file:?ProtectedFile}
	 */
	private function result( string $outcome, int $file_id = 0, string $nonce = '', string $reason = '', ?ProtectedFile $file = null ): array {
		return [
			'outcome' => $outcome,
			'file_id' => $file_id,
			'nonce'   => $nonce,
			'reason'  => $reason,
			'file'    => $file,
		];
	}

	private function deny_now( int $status, string $message ): void {
		status_header( $status );
		nocache_headers();
		wp_die(
			esc_html( $message ),
			esc_html__( 'RayEtun Media Protection', 'rayetun-media-protection' ),
			[ 'response' => absint( $status ) ]
		);
	}
}
