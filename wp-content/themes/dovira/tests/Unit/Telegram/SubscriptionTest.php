<?php
/**
 * Tests for the theme bot's subscriptions: which topic a message came from, and the two options a subscription writes.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\Telegram;

use Brain\Monkey\Functions;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/utils/telegram.php';

/**
 * Options live in an in-memory array; no secret is needed here.
 */
final class SubscriptionTest extends TestCase {

	/**
	 * The stored options, by name.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->alias( fn ( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: int|null}>
	 */
	public static function messages(): array {
		return [
			'a forum topic'                     => [ [ 'message_thread_id' => 172, 'is_topic_message' => true ], 172 ],
			'a forum group\'s General'          => [ [], null ],
			'a private chat'                    => [ [ 'chat' => [ 'id' => 111 ] ], null ],
			'a reply in an ordinary group'      => [ [ 'message_thread_id' => 55 ], null ],
			'a topic flag without a thread id'  => [ [ 'is_topic_message' => true ], null ],
		];
	}

	/**
	 * @param array<string, mixed> $message
	 */
	#[DataProvider( 'messages' )]
	public function test_only_a_forum_topic_gives_a_thread( array $message, ?int $expected ): void {
		self::assertSame( $expected, dovira_telegram_thread_of( $message ) );
	}

	public function test_a_reply_goes_back_into_the_topic_it_came_from(): void {
		self::assertSame(
			[ 'chat_id' => -100222, 'text' => 'Привіт', 'message_thread_id' => 172 ],
			dovira_telegram_reply_body( [ 'chat' => [ 'id' => -100222 ], 'message_thread_id' => 172, 'is_topic_message' => true ], 'Привіт' )
		);
		self::assertSame(
			[ 'chat_id' => -100222, 'text' => 'Привіт' ],
			dovira_telegram_reply_body( [ 'chat' => [ 'id' => -100222 ], 'message_thread_id' => 55 ], 'Привіт' )
		);
	}

	public function test_subscribing_from_a_topic_stores_the_chat_and_its_thread(): void {
		$this->options['telegram_bot_chats'] = [ 111 => 111 ];

		dovira_telegram_subscribe( -100222, 172 );

		self::assertSame( [ 111 => 111, -100222 => -100222 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [ -100222 => 172 ], $this->options['telegram_bot_chat_threads'] );
	}

	public function test_subscribing_from_the_main_area_forgets_an_earlier_thread(): void {
		$this->options['telegram_bot_chats']        = [ 111 => 111, -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172, -100333 => 9 ];

		dovira_telegram_subscribe( -100222, null );

		self::assertSame( [ 111 => 111, -100222 => -100222 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [ -100333 => 9 ], $this->options['telegram_bot_chat_threads'] );
	}

	public function test_subscribing_from_another_topic_moves_the_thread(): void {
		$this->options['telegram_bot_chats']        = [ -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		dovira_telegram_subscribe( -100222, 200 );

		self::assertSame( [ -100222 => -100222 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [ -100222 => 200 ], $this->options['telegram_bot_chat_threads'] );
	}

	public function test_unsubscribing_removes_the_chat_and_its_thread_only(): void {
		$this->options['telegram_bot_chats']        = [ 111 => 111, -100222 => -100222 ];
		$this->options['telegram_bot_chat_threads'] = [ -100222 => 172 ];

		dovira_telegram_unsubscribe( -100222 );

		self::assertSame( [ 111 => 111 ], $this->options['telegram_bot_chats'] );
		self::assertSame( [], $this->options['telegram_bot_chat_threads'] );
	}
}
