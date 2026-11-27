<?php
/**
 * Base class for the unit tests. Sets up Brain Monkey and stubs the WordPress functions most code
 * here calls, with their real behaviour for this site: the site timezone is Europe/London. A
 * WooCommerce shop also gets its price helpers, with two decimals.
 *
 * Fix the clock with set_now(). Code that reads current_datetime() then sees that moment.
 *
 * @package pie-custom-functions
 */

namespace PIE\CustomFunctions\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Base test case.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Stubs the common WordPress functions, and WooCommerce's price helpers.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_timezone' )->alias(
			static fn() => new \DateTimeZone( 'Europe/London' )
		);
		// WooCommerce price helpers: delete these two if the project has no WooCommerce.
		Functions\when( 'wc_add_number_precision' )->alias(
			static fn( $value, $round = true ) => $round ? round( $value * 100 ) : $value * 100
		);
		Functions\when( 'wc_remove_number_precision' )->alias(
			static fn( $value ) => $value / 100
		);
		Functions\when( '__' )->returnArg();
	}

	/**
	 * Tears down Brain Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Fixes the clock that current_datetime() reports.
	 *
	 * @param string $local_time A time in the site timezone, e.g. '2026-10-09 15:00'.
	 */
	protected function set_now( string $local_time ): void {
		$now = new \DateTimeImmutable( $local_time, new \DateTimeZone( 'Europe/London' ) );
		Functions\when( 'current_datetime' )->justReturn( $now );
	}
}
