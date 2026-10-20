<?php
/**
 * Tests for dovira_telegram_notify() on an install whose wp-config.php defines the secrets.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\Telegram;

use Brain\Monkey\Functions;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname( __DIR__, 3 ) . '/inc/utils/telegram.php';

/**
 * A PHP constant cannot be undefined, so every test here runs in a process of its
 * own: the rest of the suite never sees DOVIRA_TELEGRAM_* (SecretsUnsetTest).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class NotifyTest extends TestCase {

	private const TOKEN = '123456:test-token';

	/**
	 * Every wp_remote_get() call, in order: [ url, args ].
	 *
	 * @var list<array{0: string, 1: array<string, mixed>}>
	 */
	private array $requests = [];

	/**
	 * What get_option() answers, by option name.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function setUp(): void {
		parent::setUp();

		define( 'DOVIRA_TELEGRAM_BOT_TOKEN', self::TOKEN );
		define( 'DOVIRA_TELEGRAM_JOIN_PASSWORD', 'secret-word' );

		Functions\when( 'get_option' )->alias( fn ( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'wp_remote_get' )->alias(
			function ( string $url, array $args ): array {
				$this->requests[] = [ $url, $args ];

				return [];
			}
		);
	}

	public function test_the_secrets_come_from_the_constants(): void {
		self::assertSame( self::TOKEN, dovira_telegram_token() );
		self::assertSame( 'secret-word', dovira_telegram_join_password() );
		self::assertSame( 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage', dovira_telegram_api_url( 'sendMessage' ) );
	}

	public function test_only_the_exact_password_matches(): void {
		self::assertTrue( dovira_telegram_is_join_password( 'secret-word' ) );
		self::assertFalse( dovira_telegram_is_join_password( '' ) );
		self::assertFalse( dovira_telegram_is_join_password( 'secret-word ' ) );
		self::assertFalse( dovira_telegram_is_join_password( '12340987' ) );
	}

	public function test_every_subscribed_chat_gets_the_message(): void {
		$this->options['telegram_bot_chats'] = [ 111 => 111, -100222 => -100222 ];

		dovira_telegram_notify( 'Нове <a href="https://dovira.vet/">звернення</a>' );

		self::assertCount( 2, $this->requests );
		self::assertSame( [ 111, -100222 ], array_map( static fn ( array $request ) => $request[1]['body']['chat_id'], $this->requests ) );
		foreach ( $this->requests as [ $url, $args ] ) {
			self::assertSame( 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage', $url );
			self::assertSame( 45, $args['timeout'] );
			self::assertSame( 5, $args['redirection'] );
			self::assertSame( 'Нове <a href="https://dovira.vet/">звернення</a>', $args['body']['text'] );
			self::assertSame( 'html', $args['body']['parse_mode'] );
			self::assertSame( '{"is_disabled":true}', $args['body']['link_preview_options'] );
		}
	}

	public function test_a_chat_subscribed_from_a_topic_gets_the_message_only_there(): void {
		$this->options['telegram_bot_chats']        = [ -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertCount( 1, $this->requests );
		self::assertSame( -100222, $this->requests[0][1]['body']['chat_id'] );
		self::assertSame( 172, $this->requests[0][1]['body']['message_thread_id'] );
	}

	public function test_a_thread_never_carries_over_to_the_next_chat(): void {
		$this->options['telegram_bot_chats']        = [ 111 => 111, -100222 => -100222, 333 => 333 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame(
			[ [ 111, null ], [ -100222, 172 ], [ 333, null ] ],
			array_map( static fn ( array $request ) => [ $request[1]['body']['chat_id'], $request[1]['body']['message_thread_id'] ?? null ], $this->requests )
		);
	}

	public function test_no_subscribers_means_no_request(): void {
		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [], $this->requests );
	}
}
