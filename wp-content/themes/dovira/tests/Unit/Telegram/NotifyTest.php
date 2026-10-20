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

	/**
	 * The chat ids and thread ids of the requests sent, in order; null for a request without a thread.
	 *
	 * @return list<array{0: mixed, 1: mixed}>
	 */
	private function sent(): array {
		return array_map( static fn ( array $request ) => [ $request[1]['body']['chat_id'], $request[1]['body']['message_thread_id'] ?? null ], $this->requests );
	}

	public function test_the_thread_named_in_wp_config_gets_the_message_after_the_subscribers(): void {
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '-100333' );
		define( 'DOVIRA_TELEGRAM_THREAD_ID', '172' );
		$this->options['telegram_bot_chats'] = [ 111 => 111, 222 => 222 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [ [ 111, null ], [ 222, null ], [ '-100333', 172 ] ], $this->sent() );
		self::assertSame( 'Нове звернення', $this->requests[2][1]['body']['text'] );
		self::assertSame( 'html', $this->requests[2][1]['body']['parse_mode'] );
		self::assertSame( 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage', $this->requests[2][0] );
	}

	public function test_without_a_thread_the_chat_itself_gets_the_message(): void {
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '-100333' );
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [ [ 111, null ], [ '-100333', null ] ], $this->sent() );
		self::assertArrayNotHasKey( 'message_thread_id', $this->requests[1][1]['body'] );
	}

	public function test_a_subscribed_chat_that_is_the_target_gets_the_message_only_in_the_thread(): void {
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '-100333' );
		define( 'DOVIRA_TELEGRAM_THREAD_ID', '172' );
		$this->options['telegram_bot_chats'] = [ 111 => 111, -100333 => -100333 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [ [ 111, null ], [ '-100333', 172 ] ], $this->sent() );
	}

	public function test_the_thread_gets_the_message_with_no_subscribers(): void {
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '-100333' );
		define( 'DOVIRA_TELEGRAM_THREAD_ID', '172' );

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [ [ '-100333', 172 ] ], $this->sent() );
	}

	public function test_a_malformed_thread_id_sends_nothing_to_the_chat_and_is_logged(): void {
		$this->expectErrorLog();
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '-100333' );
		define( 'DOVIRA_TELEGRAM_THREAD_ID', 'abc' );
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertNull( dovira_telegram_target() );
		self::assertSame( [ [ 111, null ] ], $this->sent() );
	}

	public function test_a_malformed_chat_id_sends_nothing_to_it_and_is_logged(): void {
		$this->expectErrorLog();
		define( 'DOVIRA_TELEGRAM_CHAT_ID', '@dovira_group' );
		define( 'DOVIRA_TELEGRAM_THREAD_ID', '172' );
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [ [ 111, null ] ], $this->sent() );
	}

	public function test_no_subscribers_means_no_request(): void {
		dovira_telegram_notify( 'Нове звернення' );

		self::assertSame( [], $this->requests );
	}
}
