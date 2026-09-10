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
	 * The form posts to options.php with its nonce and prints its sections.
	 *
	 * The notices are wp-admin's business: it requires options-head.php for
	 * every screen under Settings, which prints them already.
	 */
	public function test_the_page_prints_the_nonce_the_form_and_the_sections(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_url' )->returnArg();
		// The run log names the build it is running and links to the readme.
		Functions\when( 'get_file_data' )->justReturn( array( 'Version' => '0.1.0' ) );
		Functions\when( 'plugins_url' )->justReturn( 'https://dovira.vet/wp-content/plugins/ga-telegram-bridge/readme.txt' );
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\expect( 'settings_errors' )->never();
		Functions\expect( 'settings_fields' )->once()->with( 'gatb_settings' );
		Functions\expect( 'do_settings_sections' )->once()->with( 'gatb-settings' );
		// Five times: the settings form and the four buttons below it.
		Functions\expect( 'submit_button' )->times( 5 );

		$markup = $this->render(
			static function (): void {
				Admin::render_page();
			}
		);

		$this->assertStringContainsString( '<form action="options.php" method="post">', $markup );
		$this->assertStringContainsString( 'admin-post.php', $markup, 'the connection check is on the same screen' );
	}

	/**
	 * The schedule section says when the report is due next, through the same
	 * wp_date() the log rows go through — stubbed here as UTC, so what this
	 * asserts is the line and its value, not the zone.
	 */
	public function test_the_schedule_section_says_when_the_next_run_is(): void {
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, ?int $timestamp = null ): string => gmdate( $format, (int) $timestamp )
		);

		$markup = $this->render(
			static function (): void {
				Admin::render_schedule_notes( 1789020000, false, 0, 1788933600 );
			}
		);

		$this->assertStringContainsString( 'Next run: 2026-09-10 06:00', $markup );
		$this->assertStringNotContainsString( 'notice-warning', $markup, 'nothing is wrong here' );
	}

	/**
	 * An install where nothing is registered yet is told what to do about it,
	 * rather than shown an empty line or a zero.
	 */
	public function test_the_schedule_section_says_when_nothing_is_scheduled(): void {
		$markup = $this->render(
			static function (): void {
				Admin::render_schedule_notes( null, false, 0, 1788933600 );
			}
		);

		$this->assertStringContainsString( 'not scheduled yet', $markup );
		$this->assertStringNotContainsString( 'Next run:', $markup );
	}

	/**
	 * A site that switched WP-Cron off and put nothing in its place is warned,
	 * because on that site nothing at all will send the report.
	 */
	public function test_the_screen_warns_when_nothing_calls_wp_cron(): void {
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, ?int $timestamp = null ): string => gmdate( $format, (int) $timestamp )
		);
		Functions\when( 'site_url' )->alias(
			static fn( string $path = '' ): string => 'https://dovira.vet/' . $path
		);
		$now = 1788933600;

		$warned = $this->render(
			static function () use ( $now ): void {
				Admin::render_schedule_notes( 1789020000, true, 0, $now );
			}
		);

		$this->assertStringContainsString( 'notice notice-warning', $warned );
		$this->assertStringContainsString( 'https://dovira.vet/wp-cron.php', $warned );
		$this->assertStringContainsString( 'Next run:', $warned, 'the next run is still printed' );

		$visited = $this->render(
			static function () use ( $now ): void {
				Admin::render_schedule_notes( 1789020000, true, $now - 3600, $now );
			}
		);
		$this->assertStringNotContainsString( 'notice-warning', $visited, 'a system cron is calling it' );

		$wp_cron_on = $this->render(
			static function () use ( $now ): void {
				Admin::render_schedule_notes( 1789020000, false, 0, $now );
			}
		);
		$this->assertStringNotContainsString( 'notice-warning', $wp_cron_on, 'WP-Cron is doing its job' );
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

	/**
	 * A readme URL of the shape plugins_url() builds on a real install.
	 */
	private const README_URL = 'https://dovira.vet/wp-content/plugins/ga-telegram-bridge/readme.txt';

	/**
	 * The run log says which build produced its rows.
	 *
	 * The header is stubbed rather than read: a version written into the markup
	 * would satisfy an assertion on the screen just as well, and would then be
	 * free to disagree with the header WordPress shows on the Plugins screen.
	 */
	public function test_the_run_log_names_the_build_it_is_running(): void {
		Functions\when( 'plugins_url' )->justReturn( self::README_URL );
		Functions\expect( 'get_file_data' )
			->once()
			->with( GATB_PLUGIN_FILE, array( 'Version' => 'Version' ) )
			->andReturn( array( 'Version' => '9.9.9' ) );

		$markup = $this->render( static fn() => Admin::render_log_section( array() ) );

		$this->assertStringContainsString( 'Plugin version 9.9.9', $markup );
	}

	/**
	 * Negative check: the version stands above the table, not in it.
	 *
	 * A version repeated per row would be the seventh column this screen does
	 * not have — the log records what a run did, not what it was made by.
	 */
	public function test_the_version_is_printed_once_however_many_runs_there_are(): void {
		Functions\when( 'plugins_url' )->justReturn( self::README_URL );
		Functions\when( 'get_file_data' )->justReturn( array( 'Version' => '9.9.9' ) );
		Functions\when( 'wp_date' )->justReturn( '2026-09-09 09:00' );

		$markup = $this->render(
			fn() => Admin::render_log_section( $this->five_runs() )
		);

		$this->assertSame( 1, substr_count( $markup, 'Plugin version' ) );
		$this->assertSame( 5, substr_count( $markup, '<td>Sent</td>' ), 'five runs are in the table' );
	}

	/**
	 * Every section that raises a question links to the readme answering it.
	 *
	 * Asserted on the markup a reader sees, not on the helper that builds it,
	 * and each link says which part of the readme it leads to: readme.txt is a
	 * plain-text file, so it opens at the top and is read by scrolling.
	 */
	public function test_every_section_points_at_the_readme(): void {
		Functions\expect( 'plugins_url' )
			->times( 4 )
			->with( 'readme.txt', GATB_PLUGIN_FILE )
			->andReturn( self::README_URL );
		Functions\when( 'get_file_data' )->justReturn( array( 'Version' => '0.1.0' ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array() );

		$sections = array(
			'Installation step 1.'        => $this->render( static fn() => Admin::render_google_section() ),
			'Installation step 2.'        => $this->render( static fn() => Admin::render_telegram_section() ),
			'Installation step 5.'        => $this->render( static fn() => Admin::render_schedule_section() ),
			'Frequently Asked Questions.' => $this->render( static fn() => Admin::render_log_section( array() ) ),
		);

		foreach ( $sections as $where => $markup ) {
			$this->assertStringContainsString(
				'<a href="' . self::README_URL . '" target="_blank" rel="noopener noreferrer">',
				$markup,
				'the section pointing at ' . $where . ' links to the readme'
			);
			$this->assertStringContainsString( $where, $markup, 'the link says which part of the readme it leads to' );
		}
	}

	/**
	 * Five runs of the same shape RunLog stores.
	 *
	 * @return list<array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}>
	 */
	private function five_runs(): array {
		$runs = array();

		for ( $day = 5; $day >= 1; $day-- ) {
			$runs[] = array(
				'time'    => 1789020000 - ( $day * 86400 ),
				'trigger' => 'cron',
				'date'    => sprintf( '2026-09-%02d', $day ),
				'status'  => 'sent',
				'attempt' => 1,
				'message' => 'The report was sent.',
			);
		}

		return $runs;
	}
}
