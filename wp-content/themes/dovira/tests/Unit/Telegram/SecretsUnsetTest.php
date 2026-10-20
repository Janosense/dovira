<?php
/**
 * Tests for the theme's Telegram helpers on an install whose wp-config.php does not define the secrets.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\Telegram;

use Brain\Monkey\Functions;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/utils/telegram.php';

/**
 * Runs in the suite's own process, where DOVIRA_TELEGRAM_* are never defined:
 * the tests that define them run in separate processes.
 */
final class SecretsUnsetTest extends TestCase {

	public function test_the_secrets_are_empty_without_their_constants(): void {
		self::assertFalse( defined( 'DOVIRA_TELEGRAM_BOT_TOKEN' ) );
		self::assertFalse( defined( 'DOVIRA_TELEGRAM_JOIN_PASSWORD' ) );
		self::assertSame( '', dovira_telegram_token() );
		self::assertSame( '', dovira_telegram_join_password() );
	}

	public function test_no_text_is_the_join_password_while_it_is_unset(): void {
		self::assertFalse( dovira_telegram_is_join_password( '' ) );
		self::assertFalse( dovira_telegram_is_join_password( '12340987' ) );
	}

	public function test_without_a_token_nothing_is_sent_and_the_error_is_logged(): void {
		$this->expectErrorLog();
		Functions\when( 'get_option' )->justReturn( [ 111 => 111 ] );
		$requests = 0;
		Functions\when( 'wp_remote_get' )->alias(
			static function () use ( &$requests ) {
				++$requests;

				return [];
			}
		);

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( 0, $requests );
	}

	public function test_without_subscribers_nothing_is_sent(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		$requests = 0;
		Functions\when( 'wp_remote_get' )->alias(
			static function () use ( &$requests ) {
				++$requests;

				return [];
			}
		);

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( 0, $requests );
	}
}
