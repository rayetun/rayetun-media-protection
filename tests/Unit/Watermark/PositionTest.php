<?php
/**
 * Unit tests for the Position enum's anchor math.
 *
 * @package Rayetun\MarkGuard\Tests
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Tests\Unit\Watermark;

use PHPUnit\Framework\TestCase;
use Rayetun\MarkGuard\Watermark\Position;

final class PositionTest extends TestCase {

	public function test_top_left_with_zero_offset(): void {
		$this->assertSame( [ 0, 0 ], Position::TOP_LEFT->compute_xy( 1000, 800, 100, 50, 0, 0, 'px' ) );
	}

	public function test_top_left_with_pixel_offset_pushes_inward(): void {
		$this->assertSame( [ 20, 30 ], Position::TOP_LEFT->compute_xy( 1000, 800, 100, 50, 20, 30, 'px' ) );
	}

	public function test_top_right_with_pixel_offset_pushes_inward(): void {
		// Anchor at (900, 0); positive offsets should move inward: 900 - 20 = 880 for x.
		$this->assertSame( [ 880, 30 ], Position::TOP_RIGHT->compute_xy( 1000, 800, 100, 50, 20, 30, 'px' ) );
	}

	public function test_bottom_right_with_percent_offset(): void {
		// 5% of 1000 = 50 for x, 5% of 800 = 40 for y.
		// Bottom-right anchor: (900, 750). Positive offsets move inward: (850, 710).
		$this->assertSame( [ 850, 710 ], Position::BOTTOM_RIGHT->compute_xy( 1000, 800, 100, 50, 5, 5, 'percent' ) );
	}

	public function test_center_is_centered_and_ignores_offset_sign(): void {
		// Center anchor: ((1000-100)/2, (800-50)/2) = (450, 375). Center ignores sign_x/sign_y.
		$this->assertSame( [ 450, 375 ], Position::CENTER->compute_xy( 1000, 800, 100, 50, 20, 20, 'px' ) );
	}

	public function test_bottom_center_only_shifts_vertically(): void {
		// Anchor: ((1000-100)/2, 800-50) = (450, 750). offset_x is dead (sign 0), offset_y moves up.
		$this->assertSame( [ 450, 730 ], Position::BOTTOM_CENTER->compute_xy( 1000, 800, 100, 50, 20, 20, 'px' ) );
	}
}
