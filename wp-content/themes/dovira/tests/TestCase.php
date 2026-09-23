<?php
/**
 * Base test case: sets Brain\Monkey up and down around every test.
 */

declare( strict_types=1 );

namespace dovira\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Every theme unit test extends this. No WordPress is loaded; its functions are stubbed.
 */
abstract class TestCase extends PhpUnitTestCase {

	/**
	 * Starts Brain\Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Stops Brain\Monkey and verifies its expectations.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
