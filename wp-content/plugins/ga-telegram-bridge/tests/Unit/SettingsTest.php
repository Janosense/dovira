<?php
/**
 * Tests for the settings: defaults, validation and typed access.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers Settings::sanitize_settings() and the getters around it.
 */
final class SettingsTest extends TestCase {

	/**
	 * A key file that passes validation.
	 */
	private const VALID_KEY_JSON = '{"type":"service_account","client_email":"reporter@example.iam.gserviceaccount.com","private_key":"-----BEGIN PRIVATE KEY-----\nnot-a-real-key\n-----END PRIVATE KEY-----\n","token_uri":"https://oauth2.googleapis.com/token"}';

	/**
	 * Stubs the translation functions the validation messages go through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	/**
	 * Returns the settings a configured site would have stored.
	 *
	 * @return array<string, mixed>
	 */
	private function stored(): array {
		return array(
			'property_id'          => '533779496',
			'service_account_json' => self::VALID_KEY_JSON,
			'telegram_bot_token'   => 'stored-token',
			'telegram_chat_id'     => '-1001234567890',
			'send_time'            => '08:30',
			'max_attempts'         => 5,
			'blocks'               => array_fill_keys( Settings::BLOCKS, true ),
		);
	}

	/**
	 * A fresh installation reports every block on and no credentials.
	 */
	public function test_defaults_enable_every_block_and_configure_nothing(): void {
		$defaults = Settings::defaults();

		$this->assertSame( '', $defaults['property_id'] );
		$this->assertSame( '', $defaults['service_account_json'] );
		$this->assertSame( '', $defaults['telegram_bot_token'] );
		$this->assertSame( '', $defaults['telegram_chat_id'] );
		$this->assertSame( '09:00', $defaults['send_time'] );
		$this->assertSame( 3, $defaults['max_attempts'] );
		$this->assertSame(
			array(
				'visitors' => true,
				'pages'    => true,
				'channels' => true,
				'cities'   => true,
				'devices'  => true,
			),
			$defaults['blocks']
		);
	}

	/**
	 * A valid submission is stored with the types the getters promise.
	 */
	public function test_a_valid_submission_is_stored_and_typed(): void {
		$result = Settings::sanitize_settings(
			array(
				'property_id'          => ' 123456789 ',
				'service_account_json' => self::VALID_KEY_JSON,
				'telegram_bot_token'   => ' 123456:AAbbCC ',
				'telegram_chat_id'     => '-1009876543210',
				'send_time'            => '23:59',
				'max_attempts'         => '7',
			),
			Settings::defaults()
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '123456789', $result['values']['property_id'] );
		$this->assertSame( self::VALID_KEY_JSON, $result['values']['service_account_json'] );
		$this->assertSame( '123456:AAbbCC', $result['values']['telegram_bot_token'] );
		$this->assertSame( '-1009876543210', $result['values']['telegram_chat_id'] );
		$this->assertSame( '23:59', $result['values']['send_time'] );
		$this->assertSame( 7, $result['values']['max_attempts'] );
	}

	/**
	 * An install that is not configured yet can still save the form.
	 */
	public function test_an_empty_submission_is_accepted_without_errors(): void {
		$result = Settings::sanitize_settings(
			array(
				'property_id'          => '',
				'service_account_json' => '',
				'telegram_bot_token'   => '',
				'telegram_chat_id'     => '',
				'send_time'            => '',
				'max_attempts'         => '',
			),
			Settings::defaults()
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '', $result['values']['property_id'] );
		$this->assertSame( '', $result['values']['service_account_json'] );
		$this->assertSame( '', $result['values']['telegram_chat_id'] );
	}

	/**
	 * A property id that is not digits is refused and the stored id survives.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_invalid_property_ids' )]
	public function test_an_invalid_property_id_keeps_the_stored_one( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array(
				'property_id'      => $submitted,
				'telegram_chat_id' => '-100999',
			),
			$this->stored()
		);

		$this->assertArrayHasKey( 'property_id', $result['errors'] );
		$this->assertSame( '533779496', $result['values']['property_id'] );
		$this->assertSame( '-100999', $result['values']['telegram_chat_id'], 'the rest of the submission is still saved' );
	}

	/**
	 * Property ids that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_property_ids(): array {
		return array(
			'letters'           => array( 'abc' ),
			'digits and a typo' => array( '53a' ),
			'the resource name' => array( 'properties/533779496' ),
			'a negative number' => array( '-533779496' ),
		);
	}

	/**
	 * A key file that is not usable is refused and the stored one survives.
	 *
	 * @param string $submitted The value the admin pasted.
	 */
	#[DataProvider( 'provide_invalid_service_account_json' )]
	public function test_an_unusable_service_account_key_keeps_the_stored_one( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'service_account_json' => $submitted ),
			$this->stored()
		);

		$this->assertArrayHasKey( 'service_account_json', $result['errors'] );
		$this->assertSame( self::VALID_KEY_JSON, $result['values']['service_account_json'] );
	}

	/**
	 * Key files that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_service_account_json(): array {
		return array(
			'not json at all'      => array( 'paste your key here' ),
			'truncated json'       => array( '{"client_email":"a@b.c",' ),
			'a json list'          => array( '["client_email","private_key","token_uri"]' ),
			'no private_key'       => array( '{"client_email":"a@b.c","token_uri":"https://oauth2.googleapis.com/token"}' ),
			'an empty private_key' => array( '{"client_email":"a@b.c","private_key":"   ","token_uri":"https://oauth2.googleapis.com/token"}' ),
			'no token_uri'         => array( '{"client_email":"a@b.c","private_key":"-----BEGIN PRIVATE KEY-----"}' ),
		);
	}

	/**
	 * The rejection never repeats what was submitted: it may be a private key.
	 */
	public function test_the_key_error_message_quotes_nothing_of_the_submission(): void {
		$submitted = '{"private_key":"-----BEGIN PRIVATE KEY-----\nSECRETMATERIAL\n-----END PRIVATE KEY-----"}';

		$result = Settings::sanitize_settings(
			array( 'service_account_json' => $submitted ),
			$this->stored()
		);

		$this->assertStringNotContainsString( 'SECRETMATERIAL', $result['errors']['service_account_json'] );
		$this->assertStringNotContainsString( 'PRIVATE KEY', $result['errors']['service_account_json'] );
	}

	/**
	 * A chat id that is not a number is refused and the stored one survives.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_invalid_chat_ids' )]
	public function test_an_invalid_chat_id_keeps_the_stored_one( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'telegram_chat_id' => $submitted ),
			$this->stored()
		);

		$this->assertArrayHasKey( 'telegram_chat_id', $result['errors'] );
		$this->assertSame( '-1001234567890', $result['values']['telegram_chat_id'] );
	}

	/**
	 * Chat ids that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_chat_ids(): array {
		return array(
			'a channel username' => array( '@dovira' ),
			'letters in it'      => array( '12a' ),
			'an invite link'     => array( 'https://t.me/dovira' ),
		);
	}

	/**
	 * Both shapes of a real chat id are accepted.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_valid_chat_ids' )]
	public function test_a_numeric_chat_id_is_accepted( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'telegram_chat_id' => $submitted ),
			$this->stored()
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( $submitted, $result['values']['telegram_chat_id'] );
	}

	/**
	 * Chat ids that must be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_valid_chat_ids(): array {
		return array(
			'a private chat' => array( '123456789' ),
			'a channel'      => array( '-1001234567890' ),
		);
	}

	/**
	 * A send time that is not HH:MM is refused and the stored one survives.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_invalid_send_times' )]
	public function test_an_invalid_send_time_keeps_the_stored_one( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'send_time' => $submitted ),
			$this->stored()
		);

		$this->assertArrayHasKey( 'send_time', $result['errors'] );
		$this->assertSame( '08:30', $result['values']['send_time'] );
	}

	/**
	 * Send times that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_send_times(): array {
		return array(
			'past midnight'   => array( '24:00' ),
			'a missing zero'  => array( '9:00' ),
			'a minute over'   => array( '09:60' ),
			'words'           => array( 'noon' ),
			'seconds as well' => array( '09:00:00' ),
		);
	}

	/**
	 * The edges of the day are valid times.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_valid_send_times' )]
	public function test_a_valid_send_time_is_accepted( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'send_time' => $submitted ),
			$this->stored()
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( $submitted, $result['values']['send_time'] );
	}

	/**
	 * Send times that must be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_valid_send_times(): array {
		return array(
			'midnight'        => array( '00:00' ),
			'the last minute' => array( '23:59' ),
		);
	}

	/**
	 * An attempt count outside 1-10 is refused and the stored one survives.
	 *
	 * @param string $submitted The value the admin typed.
	 */
	#[DataProvider( 'provide_invalid_max_attempts' )]
	public function test_an_invalid_attempt_count_keeps_the_stored_one( string $submitted ): void {
		$result = Settings::sanitize_settings(
			array( 'max_attempts' => $submitted ),
			$this->stored()
		);

		$this->assertArrayHasKey( 'max_attempts', $result['errors'] );
		$this->assertSame( 5, $result['values']['max_attempts'] );
	}

	/**
	 * Attempt counts that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_max_attempts(): array {
		return array(
			'none at all'    => array( '0' ),
			'one too many'   => array( '11' ),
			'not a number'   => array( 'abc' ),
			'a fraction'     => array( '2.5' ),
			'a negative one' => array( '-3' ),
		);
	}

	/**
	 * Both ends of the allowed range are accepted.
	 *
	 * @param string $submitted The value the admin typed.
	 * @param int    $expected  The value that must be stored.
	 */
	#[DataProvider( 'provide_valid_max_attempts' )]
	public function test_a_valid_attempt_count_is_accepted( string $submitted, int $expected ): void {
		$result = Settings::sanitize_settings(
			array( 'max_attempts' => $submitted ),
			$this->stored()
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( $expected, $result['values']['max_attempts'] );
	}

	/**
	 * Attempt counts that must be accepted.
	 *
	 * @return array<string, array{string, int}>
	 */
	public static function provide_valid_max_attempts(): array {
		return array(
			'the minimum' => array( '1', 1 ),
			'the maximum' => array( '10', 10 ),
		);
	}

	/**
	 * Every optional block follows the checkboxes; unknown keys are dropped.
	 */
	public function test_the_block_switches_follow_the_submitted_checkboxes(): void {
		$result = Settings::sanitize_settings(
			array(
				'blocks' => array(
					'pages'    => '1',
					'channels' => '0',
					'devices'  => '1',
					'weather'  => '1',
				),
			),
			$this->stored()
		);

		$this->assertSame(
			array(
				'visitors' => true,
				'pages'    => true,
				'channels' => false,
				'cities'   => false,
				'devices'  => true,
			),
			$result['values']['blocks'],
			'an unchecked box is off whether it arrives as 0 or not at all'
		);
	}

	/**
	 * The visitors block is on however the form or the option says otherwise.
	 */
	public function test_the_visitors_block_can_never_be_switched_off(): void {
		$result = Settings::sanitize_settings(
			array( 'blocks' => array( 'visitors' => '0' ) ),
			$this->stored()
		);

		$this->assertTrue( $result['values']['blocks']['visitors'] );

		$stored           = $this->stored();
		$stored['blocks'] = array_fill_keys( Settings::BLOCKS, false );

		$this->assertTrue( Settings::merge_defaults( $stored )['blocks']['visitors'] );
	}

	/**
	 * A submission that touches nothing leaves the stored blocks alone.
	 */
	public function test_a_submission_without_blocks_leaves_them_untouched(): void {
		$stored           = $this->stored();
		$stored['blocks'] = array(
			'visitors' => true,
			'pages'    => false,
			'channels' => true,
			'cities'   => false,
			'devices'  => true,
		);

		$result = Settings::sanitize_settings( array( 'property_id' => '42' ), $stored );

		$this->assertSame( $stored['blocks'], $result['values']['blocks'] );
	}

	/**
	 * A secret that comes from wp-config.php ignores what the form posted.
	 */
	public function test_a_locked_secret_ignores_the_submission_without_an_error(): void {
		$result = Settings::sanitize_settings(
			array(
				'service_account_json' => 'nonsense that would otherwise be refused',
				'telegram_bot_token'   => 'a token typed into a read-only field',
			),
			$this->stored(),
			array( 'service_account_json', 'telegram_bot_token' )
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( self::VALID_KEY_JSON, $result['values']['service_account_json'] );
		$this->assertSame( 'stored-token', $result['values']['telegram_bot_token'] );
	}

	/**
	 * Without a constant, the stored value is the secret.
	 */
	public function test_an_undefined_constant_leaves_the_stored_secret_in_place(): void {
		$this->assertSame(
			'from-the-option',
			Settings::secret( 'GATB_A_CONSTANT_NO_TEST_DEFINES', 'from-the-option' )
		);
	}

	/**
	 * A half-written option still answers every getter with the right type.
	 */
	public function test_a_partial_option_is_completed_with_the_defaults(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'property_id'  => 533779496,
				'max_attempts' => '4',
			)
		);

		$this->assertSame( '533779496', Settings::property_id() );
		$this->assertSame( 4, Settings::max_attempts() );
		$this->assertSame( '09:00', Settings::send_time() );
		$this->assertSame( '', Settings::telegram_chat_id() );
		$this->assertTrue( Settings::is_block_enabled( 'visitors' ) );
		$this->assertFalse( Settings::is_block_enabled( 'weather' ) );
	}

	/**
	 * A stored option that switched blocks off is reported as it is.
	 */
	public function test_the_getters_report_the_stored_block_switches(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'blocks' => array(
					'visitors' => false,
					'pages'    => true,
					'cities'   => true,
				),
			)
		);

		$this->assertTrue( Settings::is_block_enabled( 'visitors' ), 'visitors is always sent' );
		$this->assertTrue( Settings::is_block_enabled( 'pages' ) );
		$this->assertFalse( Settings::is_block_enabled( 'channels' ) );
		$this->assertSame(
			array( 'visitors', 'pages', 'channels', 'cities', 'devices' ),
			array_keys( Settings::blocks() )
		);
	}

	/**
	 * A broken option value never reaches a getter as anything but its type.
	 */
	public function test_a_corrupt_option_falls_back_to_the_defaults(): void {
		Functions\when( 'get_option' )->justReturn( 'not an array at all' );

		$this->assertSame( '', Settings::property_id() );
		$this->assertSame( 3, Settings::max_attempts() );
		$this->assertSame( '09:00', Settings::send_time() );
	}

	/**
	 * Registering the option hands the Settings API its sanitize callback.
	 */
	public function test_register_declares_the_option_with_its_sanitizer(): void {
		Functions\expect( 'register_setting' )
			->once()
			->with(
				'gatb_settings',
				'gatb_settings',
				\Mockery::on(
					static function ( $arguments ): bool {
						return array( Settings::class, 'sanitize' ) === $arguments['sanitize_callback']
							&& Settings::defaults() === $arguments['default']
							&& false === $arguments['show_in_rest'];
					}
				)
			);

		Settings::register();

		$this->assertSame( 'gatb_settings', Settings::OPTION );
	}

	/**
	 * Every rejected field becomes one admin notice on the settings screen.
	 */
	public function test_sanitize_reports_each_rejected_field_to_the_admin(): void {
		Functions\when( 'get_option' )->justReturn( $this->stored() );
		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'gatb_settings', 'gatb_property_id', \Mockery::type( 'string' ), 'error' );

		$values = Settings::sanitize( array( 'property_id' => 'abc' ) );

		$this->assertSame( '533779496', $values['property_id'] );
	}
}
