<?php
/**
 * Tests for the two secrets that may come from wp-config.php.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * The only test class that defines the plugin's real secret constants.
 *
 * A constant cannot be undefined once it is set, so no other test may assert
 * the "not defined" branch of these two names — SettingsTest asserts it through
 * Settings::secret() with a name nothing defines.
 */
final class SettingsSecretConstantsTest extends TestCase {

	/**
	 * Defines both constants, as a site would in wp-config.php.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! defined( 'GATB_GA_SERVICE_ACCOUNT_JSON' ) ) {
			define( 'GATB_GA_SERVICE_ACCOUNT_JSON', '{"client_email":"from@config.iam.gserviceaccount.com"}' );
		}

		if ( ! defined( 'GATB_TELEGRAM_BOT_TOKEN' ) ) {
			define( 'GATB_TELEGRAM_BOT_TOKEN', '999:FROM-CONFIG' );
		}
	}

	/**
	 * The constant wins over whatever the option holds.
	 */
	public function test_the_constants_win_over_the_stored_secrets(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'service_account_json' => '{"client_email":"from@option.iam.gserviceaccount.com"}',
				'telegram_bot_token'   => 'from-the-option',
			)
		);

		$this->assertSame(
			'{"client_email":"from@config.iam.gserviceaccount.com"}',
			Settings::service_account_json()
		);
		$this->assertSame( '999:FROM-CONFIG', Settings::telegram_bot_token() );
	}

	/**
	 * Both secret fields report themselves as coming from the configuration.
	 */
	public function test_both_secrets_are_reported_as_locked(): void {
		$this->assertTrue( Settings::is_secret_locked( 'service_account_json' ) );
		$this->assertTrue( Settings::is_secret_locked( 'telegram_bot_token' ) );
		$this->assertFalse( Settings::is_secret_locked( 'telegram_chat_id' ), 'the chat id is not a secret' );
		$this->assertSame(
			array( 'service_account_json', 'telegram_bot_token' ),
			Settings::locked_keys()
		);
	}

	/**
	 * A settings save cannot overwrite a secret that comes from the configuration.
	 */
	public function test_a_save_cannot_overwrite_a_secret_from_the_configuration(): void {
		Functions\stubTranslationFunctions();
		Functions\when( 'get_option' )->justReturn(
			array(
				'service_account_json' => '{"client_email":"from@option.iam.gserviceaccount.com"}',
				'telegram_bot_token'   => 'from-the-option',
			)
		);

		$values = Settings::sanitize(
			array(
				'service_account_json' => 'anything at all',
				'telegram_bot_token'   => 'anything at all',
			)
		);

		$this->assertSame(
			'{"client_email":"from@option.iam.gserviceaccount.com"}',
			$values['service_account_json'],
			'the option keeps its value; the constant is what the getter returns'
		);
		$this->assertSame( 'from-the-option', $values['telegram_bot_token'] );
	}
}
