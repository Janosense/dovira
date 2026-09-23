<?php
/**
 * Smoke test for the theme's test bootstrap.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit;

use Brain\Monkey\Functions;
use dovira\Tests\TestCase;

/**
 * Proves the suite runs without WordPress and that Brain\Monkey can stand in for it.
 */
final class BootstrapTest extends TestCase {

	/**
	 * No WordPress is loaded, so `get_site_url()` exists only because it is stubbed.
	 *
	 * The stub uses `when()` rather than `expect()` on purpose: a Brain\Monkey
	 * expectation is not a PHPUnit assertion. Without the `assertSame()` this test
	 * would therefore assert nothing, and `failOnRisky` fails the gate on that.
	 */
	public function test_a_wordpress_function_can_be_stubbed(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://dovira.ddev.site' );

		$this->assertSame( 'https://dovira.ddev.site', get_site_url() );
	}
}
