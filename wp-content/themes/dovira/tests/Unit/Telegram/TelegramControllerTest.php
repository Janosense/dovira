<?php
/**
 * Tests for dovira\TelegramController::handle_updates(): joining by password, from a topic or not, and /reset.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\Telegram;

use Brain\Monkey\Functions;
use dovira\TelegramController;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use WP_REST_Request;

require_once dirname( __DIR__, 3 ) . '/inc/utils/telegram.php';
require_once dirname( __DIR__, 3 ) . '/inc/rest-api/TelegramController.php';

/**
 * Defines the secret constants, so every test runs in a process of its own (docs/TESTING.md).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class TelegramControllerTest extends TestCase {

	private const CONNECTED = 'Вас успішно підключено. Ви отримаєте повідомлення як тільки хтось відправить форму зворотнього звʼязку.';

	/**
	 * The stored options, by name.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * The body of every wp_remote_get() call, in order.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $replies = [];

	protected function setUp(): void {
		parent::setUp();

		define( 'DOVIRA_TELEGRAM_BOT_TOKEN', '123456:test-token' );
		define( 'DOVIRA_TELEGRAM_JOIN_PASSWORD', 'secret-word' );

		Functions\when( 'get_option' )->alias( fn ( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_remote_get' )->alias(
			function ( string $url, array $args ): array {
				$this->replies[] = $args['body'];

				return [];
			}
		);
		Functions\when( 'wp_send_json' )->justReturn( null );
	}

	/**
	 * @param array<string, mixed> $message
	 */
	private function update( array $message ): void {
		$request = new WP_REST_Request( 'POST', '/dovira/v1/telegram/handle-updates/' );
		$request->set_body_params( [ 'message' => $message ] );

		( new TelegramController() )->handle_updates( $request );
	}

	public function test_the_password_in_a_topic_subscribes_that_topic_and_answers_there(): void {
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		$this->update( [ 'chat' => [ 'id' => -100222 ], 'text' => 'secret-word', 'message_thread_id' => 172, 'is_topic_message' => true ] );

		self::assertSame( [ 111 => 111, -100222 => -100222 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [ -100222 => 172 ], $this->options['telegram_bot_chat_threads'] );
		self::assertSame( [ [ 'chat_id' => -100222, 'text' => self::CONNECTED, 'message_thread_id' => 172 ] ], $this->replies );
	}

	public function test_the_password_in_the_main_area_subscribes_the_chat_without_a_thread(): void {
		$this->options['telegram_bot_chats']        = [ -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		$this->update( [ 'chat' => [ 'id' => -100222 ], 'text' => 'secret-word' ] );

		self::assertSame( [ -100222 => -100222 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [], $this->options['telegram_bot_chat_threads'] );
		self::assertSame( [ [ 'chat_id' => -100222, 'text' => self::CONNECTED ] ], $this->replies );
	}

	public function test_a_wrong_password_changes_nothing(): void {
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		$this->update( [ 'chat' => [ 'id' => -100222 ], 'text' => '12340987', 'message_thread_id' => 172, 'is_topic_message' => true ] );

		self::assertSame( [ 111 => 111 ], $this->options['telegram_bot_chats'] );
		self::assertArrayNotHasKey( 'telegram_bot_chat_threads', $this->options );
		self::assertSame( [], $this->replies );
	}

	public function test_reset_removes_the_chat_and_its_thread_and_answers_in_the_topic(): void {
		$this->options['telegram_bot_chats']        = [ 111 => 111, -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		$this->update( [ 'chat' => [ 'id' => -100222 ], 'text' => '/reset', 'message_thread_id' => 172, 'is_topic_message' => true ] );

		self::assertSame( [ 111 => 111 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [], $this->options['telegram_bot_chat_threads'] );
		self::assertSame( [ [ 'chat_id' => -100222, 'text' => 'Дані успішно скинуто.', 'message_thread_id' => 172 ] ], $this->replies );
	}

	public function test_start_in_a_topic_is_answered_in_that_topic(): void {
		$this->update( [ 'chat' => [ 'id' => -100222 ], 'text' => '/start', 'message_thread_id' => 172, 'is_topic_message' => true ] );

		self::assertSame( [ [ 'chat_id' => -100222, 'text' => 'Для підключення до бота, будь ласка, введіть пароль:', 'message_thread_id' => 172 ] ], $this->replies );
	}
}
