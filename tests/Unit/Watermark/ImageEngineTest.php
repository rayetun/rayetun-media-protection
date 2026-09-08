<?php
/**
 * Integration tests for the ImageEngine — end-to-end run against real fixture
 * images using whichever backend (Imagick or GD) is present.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Watermark;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Watermark\Context;
use Rayetun\MarkGuard\Watermark\Engines\ImageEngine;
use Rayetun\MarkGuard\Watermark\Position;
use Rayetun\MarkGuard\Watermark\WatermarkRule;

final class ImageEngineTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp_dir = sys_get_temp_dir() . '/markguard-tests-' . bin2hex( random_bytes( 4 ) );
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
		$engine = new ImageEngine();
		$this->assertSame( 'image', $engine->id() );
		$this->assertContains( 'image/jpeg', $engine->supported_mime_types() );
		$this->assertContains( 'image/png', $engine->supported_mime_types() );
		$this->assertContains( 'image/webp', $engine->supported_mime_types() );
	}

	public function test_engine_is_available_when_gd_present(): void {
		$this->skip_unless_gd();
		$engine = new ImageEngine();
		$this->assertTrue( $engine->is_available() );
	}

	public function test_apply_text_watermark_on_png(): void {
		$this->skip_unless_gd();
		$engine = new ImageEngine();
		$source = $this->generate_test_png( 800, 600 );
		$before = filesize( $source );

		$rule = WatermarkRule::from_array(
			[
				'type'      => 'text',
				'text'      => 'MarkGuard Test',
				'position'  => 'bottom-right',
				'opacity'   => 70,
				'font_size' => 24,
			]
		);

		$result = $engine->apply( $source, $rule, Context::empty() );

		$this->assertTrue( $result->success, 'Expected success. Reason: ' . ( $result->reason ?? '(none)' ) );
		$this->assertFileExists( $result->output_path );
		$this->assertNotSame( 0, filesize( $result->output_path ) );
		// File must still be a valid image.
		$this->assertNotFalse( getimagesize( $result->output_path ) );
	}

	public function test_apply_skips_when_below_min_size(): void {
		$this->skip_unless_gd();
		$engine = new ImageEngine();
		$source = $this->generate_test_png( 100, 80 );

		$rule = WatermarkRule::from_array(
			[
				'type'             => 'text',
				'text'             => 'skip me',
				'min_source_width' => 500,
			]
		);

		$result = $engine->apply( $source, $rule, Context::empty() );

		$this->assertFalse( $result->success );
		$this->assertTrue( $result->skipped );
		$this->assertStringContainsString( 'below threshold', (string) $result->reason );
	}

	public function test_apply_returns_failure_for_unreadable_source(): void {
		$engine = new ImageEngine();
		$result = $engine->apply( $this->tmp_dir . '/does-not-exist.png', WatermarkRule::from_array( [ 'type' => 'text' ] ), Context::empty() );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'not readable', (string) $result->reason );
	}

	public function test_apply_image_overlay_scales_to_source(): void {
		$this->skip_unless_gd();
		$engine  = new ImageEngine();
		$source  = $this->generate_test_png( 800, 600, [ 200, 200, 200 ] );
		$overlay = $this->generate_test_png( 400, 400, [ 50, 200, 50 ] );

		$rule = WatermarkRule::from_array(
			[
				'type'         => 'image',
				'overlay_path' => $overlay,
				'position'     => 'center',
				'opacity'      => 80,
				'image_scale'  => 0.25,
			]
		);

		$result = $engine->apply( $source, $rule, Context::empty() );
		$this->assertTrue( $result->success, 'Expected success. Reason: ' . ( $result->reason ?? '(none)' ) );
		$this->assertNotFalse( getimagesize( $result->output_path ) );
	}

	// -----------------------------
	// Helpers
	// -----------------------------
	private function skip_unless_gd(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension not loaded in this test environment.' );
		}
	}

	/** @param array{0:int,1:int,2:int}|null $fill */
	private function generate_test_png( int $w, int $h, ?array $fill = null ): string {
		$img = imagecreatetruecolor( $w, $h );
		$fill = $fill ?? [ 240, 240, 240 ];
		imagefill( $img, 0, 0, imagecolorallocate( $img, $fill[0], $fill[1], $fill[2] ) );
		$path = $this->tmp_dir . '/img-' . bin2hex( random_bytes( 4 ) ) . '.png';
		imagepng( $img, $path );
		imagedestroy( $img );
		return $path;
	}
}
