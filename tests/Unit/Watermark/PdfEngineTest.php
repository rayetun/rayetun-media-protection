<?php
/**
 * Integration tests for the PdfEngine — runs against a real generated PDF
 * using TCPDF + FPDI from the composer vendor directory.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Watermark;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Engines\PdfEngine;
use Rayetun\MarkGuard\Watermark\TokenRegistry;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

final class PdfEngineTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\\setasign\\Fpdi\\Tcpdf\\Fpdi' ) ) {
			$this->markTestSkipped( 'TCPDF/FPDI not installed (run composer install).' );
		}
		$this->tmp_dir = sys_get_temp_dir() . '/markguard-pdf-tests-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp_dir, 0777, true );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->tmp_dir . '/*' ) ?: [] as $file ) {
			@unlink( $file );
		}
		@rmdir( $this->tmp_dir );
		parent::tearDown();
	}

	public function test_engine_id_and_supported_types(): void {
		$engine = new PdfEngine();
		$this->assertSame( 'pdf', $engine->id() );
		$this->assertSame( [ 'application/pdf' ], $engine->supported_mime_types() );
	}

	public function test_engine_is_available_when_fpdi_present(): void {
		$this->assertTrue( ( new PdfEngine() )->is_available() );
	}

	public function test_apply_text_watermark_preserves_page_count(): void {
		$engine = new PdfEngine();
		$source = $this->generate_pdf( 2 );

		$rule   = WatermarkRule::from_array(
			[
				'type'     => 'text',
				'text'     => 'CONFIDENTIAL',
				'position' => 'center',
				'opacity'  => 40,
				'rotation' => 45,
			]
		);

		$result = $engine->apply( $source, $rule, Context::empty() );

		$this->assertTrue( $result->success, 'Reason: ' . ( $result->reason ?? '(none)' ) );
		$this->assertSame( 'tcpdf-fpdi', $result->engine_used );
		$this->assertSame( '%PDF', substr( (string) file_get_contents( $result->output_path ), 0, 4 ) );
		$this->assertSame( 2, $this->page_count( $result->output_path ) );
	}

	public function test_apply_resolves_dynamic_tokens(): void {
		$registry = new TokenRegistry();
		$registry->register_defaults();
		$engine = new PdfEngine( $registry );
		$source = $this->generate_pdf( 1 );

		$rule = WatermarkRule::from_array(
			[
				'type'      => 'text',
				'text'      => '{user_email} — order {order_id}',
				'position'  => 'bottom-center',
				'opacity'   => 60,
				'font_size' => 10,
			]
		);

		$context = new Context( user_email: 'buyer@example.com', order_id: 1234 );
		$result  = $engine->apply( $source, $rule, $context );

		$this->assertTrue( $result->success, 'Reason: ' . ( $result->reason ?? '(none)' ) );
		$this->assertSame( 1, $this->page_count( $result->output_path ) );
	}

	public function test_apply_fails_on_empty_resolved_text(): void {
		$engine = new PdfEngine();
		$source = $this->generate_pdf( 1 );

		$result = $engine->apply(
			$source,
			WatermarkRule::from_array( [ 'type' => 'text', 'text' => '   ' ] ),
			Context::empty()
		);

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'empty', (string) $result->reason );
	}

	public function test_apply_fails_on_unreadable_source(): void {
		$engine = new PdfEngine();
		$result = $engine->apply(
			$this->tmp_dir . '/missing.pdf',
			WatermarkRule::from_array( [ 'type' => 'text', 'text' => 'x' ] ),
			Context::empty()
		);
		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'not readable', (string) $result->reason );
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------
	private function generate_pdf( int $pages ): string {
		$pdf = new \TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->SetFont( 'helvetica', '', 12 );
		for ( $i = 1; $i <= $pages; $i++ ) {
			$pdf->AddPage();
			$pdf->Write( 0, 'MarkGuard test document — page ' . $i );
		}
		$path = $this->tmp_dir . '/source-' . bin2hex( random_bytes( 4 ) ) . '.pdf';
		$pdf->Output( $path, 'F' );
		return $path;
	}

	private function page_count( string $path ): int {
		$fpdi = new \setasign\Fpdi\Tcpdf\Fpdi();
		return $fpdi->setSourceFile( $path );
	}
}
