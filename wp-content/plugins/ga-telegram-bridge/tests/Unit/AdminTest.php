<?php
/**
 * Tests for the settings screen: what the administrator actually sees.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Admin;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * Covers the page registration and the two field states the screen has.
 */
final class AdminTest extends TestCase {

	/**
	 * Stubs the translating and escaping functions the markup goes through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	/**
	 * Captures what a renderer prints.
	 *
	 * @param callable $renderer The call to capture.
	 */
	private function render( callable $renderer ): string {
		ob_start();
		$renderer();

		return (string) ob_get_clean();
	}

	/**
	 * Stubs the two attribute helpers WordPress prints checkboxes with.
	 */
	private function stub_checkbox_helpers(): void {
		Functions\when( 'checked' )->alias(
			static function ( $checked, $current = true ): void {
				if ( (string) $checked === (string) $current ) {
					echo ' checked="checked"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( $disabled, $current = true ): void {
				if ( (string) $disabled === (string) $current ) {
					echo ' disabled="disabled"';
				}
			}
		);
	}

	/**
	 * The screen lives under Settings and only administrators reach it.
	 */
	public function test_the_page_is_registered_under_settings_for_administrators(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->with(
				'GA → Telegram',
				'GA → Telegram',
				'manage_options',
				'gatb-settings',
				array( Admin::class, 'render_page' )
			);

		Admin::add_page();

		$this->assertSame( 'gatb-settings', Admin::PAGE );
	}

	/**
	 * A secret that comes from wp-config.php is shown as read-only and empty.
	 */
	public function test_a_locked_secret_is_read_only_and_never_printed(): void {
		$markup = $this->render(
			static function (): void {
				Admin::secret_input(
					'service_account_json',
					'{"private_key":"-----BEGIN PRIVATE KEY-----SECRETMATERIAL"}',
					true,
					true,
					'The whole JSON key file.'
				);
			}
		);

		$this->assertStringContainsString( 'readonly', $markup );
		$this->assertStringContainsString( 'Set in configuration (wp-config.php)', $markup );
		$this->assertStringContainsString( 'GATB_GA_SERVICE_ACCOUNT_JSON', $markup );
		$this->assertStringNotContainsString( 'SECRETMATERIAL', $markup );
		$this->assertStringNotContainsString( 'PRIVATE KEY', $markup );
	}

	/**
	 * Without the constant the same field is editable and shows what is stored.
	 */
	public function test_an_editable_secret_shows_the_stored_value(): void {
		$markup = $this->render(
			static function (): void {
				Admin::secret_input(
					'service_account_json',
					'{"client_email":"reporter@example.iam.gserviceaccount.com"}',
					false,
					true,
					'The whole JSON key file.'
				);
			}
		);

		$this->assertStringNotContainsString( 'readonly', $markup );
		$this->assertStringNotContainsString( 'Set in configuration', $markup );
		$this->assertStringContainsString( 'reporter@example.iam.gserviceaccount.com', $markup );
		$this->assertStringContainsString( 'The whole JSON key file.', $markup );
	}

	/**
	 * The bot token is a single-line field and follows the same two states.
	 */
	public function test_the_token_field_is_a_single_line_input_and_can_be_locked(): void {
		$editable = $this->render(
			static function (): void {
				Admin::secret_input( 'telegram_bot_token', '123456:AAbbCC', false, false, 'The BotFather token.' );
			}
		);

		$this->assertStringContainsString( '<input', $editable );
		$this->assertStringContainsString( 'value="123456:AAbbCC"', $editable );

		$locked = $this->render(
			static function (): void {
				Admin::secret_input( 'telegram_bot_token', '123456:AAbbCC', true, false, 'The BotFather token.' );
			}
		);

		$this->assertStringContainsString( 'readonly', $locked );
		$this->assertStringContainsString( 'GATB_TELEGRAM_BOT_TOKEN', $locked );
		$this->assertStringNotContainsString( '123456:AAbbCC', $locked );
	}

	/**
	 * The visitors block is offered as ticked and unchangeable.
	 */
	public function test_the_visitors_block_is_checked_and_disabled(): void {
		$this->stub_checkbox_helpers();

		$markup = $this->render(
			static function (): void {
				Admin::block_checkboxes( array_fill_keys( Settings::BLOCKS, false ) );
			}
		);

		$this->assertMatchesRegularExpression(
			'/id="gatb_block_visitors"[^>]*checked="checked"[^>]*disabled="disabled"/',
			$markup
		);
	}

	/**
	 * Every other block reflects what is stored and stays changeable.
	 */
	public function test_an_optional_block_follows_the_stored_switch(): void {
		$this->stub_checkbox_helpers();

		$markup = $this->render(
			static function (): void {
				Admin::block_checkboxes(
					array(
						'visitors' => true,
						'pages'    => true,
						'channels' => false,
						'cities'   => false,
						'devices'  => true,
					)
				);
			}
		);

		$this->assertMatchesRegularExpression( '/id="gatb_block_pages"[^>]*checked="checked"/', $markup );
		$this->assertDoesNotMatchRegularExpression( '/id="gatb_block_channels"[^>]*checked="checked"/', $markup );
		$this->assertDoesNotMatchRegularExpression( '/id="gatb_block_pages"[^>]*disabled="disabled"/', $markup );
	}

	/**
	 * An unchecked box still reaches the sanitizer, as a zero.
	 */
	public function test_every_block_posts_a_value_even_when_it_is_unchecked(): void {
		$this->stub_checkbox_helpers();

		$markup = $this->render(
			static function (): void {
				Admin::block_checkboxes( array_fill_keys( Settings::BLOCKS, false ) );
			}
		);

		foreach ( Settings::BLOCKS as $block ) {
			$this->assertStringContainsString(
				'<input type="hidden" name="gatb_settings[blocks][' . $block . ']" value="0" />',
				$markup
			);
		}
	}

	/**
	 * A number field carries the bounds the sanitizer enforces.
	 */
	public function test_the_attempt_field_carries_its_bounds(): void {
		$markup = $this->render(
			static function (): void {
				Admin::text_input(
					'max_attempts',
					'3',
					'number',
					'small-text',
					array(
						'min' => '1',
						'max' => '10',
					),
					'Between 1 and 10.'
				);
			}
		);

		$this->assertStringContainsString( 'name="gatb_settings[max_attempts]"', $markup );
		$this->assertStringContainsString( 'value="3"', $markup );
		$this->assertStringContainsString( 'min="1"', $markup );
		$this->assertStringContainsString( 'max="10"', $markup );
	}

	/**
	 * The form posts to options.php with its nonce and shows the notices.
	 */
	public function test_the_page_prints_the_nonce_the_notices_and_the_sections(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'settings_errors' )->once()->with( 'gatb_settings' );
		Functions\expect( 'settings_fields' )->once()->with( 'gatb_settings' );
		Functions\expect( 'do_settings_sections' )->once()->with( 'gatb-settings' );
		Functions\expect( 'submit_button' )->once();

		$markup = $this->render(
			static function (): void {
				Admin::render_page();
			}
		);

		$this->assertStringContainsString( '<form action="options.php" method="post">', $markup );
	}

	/**
	 * Somebody without the capability is shown nothing at all.
	 */
	public function test_the_page_prints_nothing_without_the_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$markup = $this->render(
			static function (): void {
				Admin::render_page();
			}
		);

		$this->assertSame( '', $markup );
	}
}
