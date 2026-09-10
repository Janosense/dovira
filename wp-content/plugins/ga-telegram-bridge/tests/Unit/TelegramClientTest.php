<?php
/**
 * Tests for the Telegram client: the request it makes, and what it says about
 * every way the call can fail.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Tests\TestCase;
use GaTelegramBridge\TelegramClient;
use GaTelegramBridge\TelegramException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The error mapping runs on the payloads in tests/fixtures/telegram/; only the
 * network boundary is stubbed.
 */
final class TelegramClientTest extends TestCase {

	/**
	 * A token of the right shape that belongs to no bot.
	 */
	private const TOKEN = '111111111:AAtest-token-that-belongs-to-no-bot';

	/**
	 * A channel id of the shape Telegram documents.
	 */
	private const CHAT_ID = '-1001234567890';

	/**
	 * How long each attempt was asked to wait, in order.
	 *
	 * @var list<int>
	 */
	private array $waited = array();

	/**
	 * Stubs the WordPress helpers every path here goes through.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->waited = array();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
		);
		Functions\when( 'get_option' )->justReturn( array( 'telegram_bot_token' => self::TOKEN ) );
	}

	/**
	 * Sends the test message, recording any wait instead of sitting through it.
	 *
	 * No test in this suite sleeps: the client takes the wait as an argument
	 * the way the rest of the plugin takes the clock.
	 */
	private function send(): void {
		TelegramClient::send_message(
			self::CHAT_ID,
			'<b>Dovira</b>',
			function ( int $seconds ): void {
				$this->waited[] = $seconds;
			}
		);
	}

	/**
	 * Counts the posts and answers each of them from the given list.
	 *
	 * The last answer stands for every attempt after it, so a test that repeats
	 * one refusal passes it once.
	 *
	 * @param list<array{response: array{code: int}, body: string}> $answers The answers, in order.
	 * @param int                                                   $posted  Counts the attempts.
	 */
	private function answer_with( array $answers, int &$posted ): void {
		Functions\when( 'wp_remote_post' )->alias(
			static function () use ( $answers, &$posted ): array {
				$answer = $answers[ min( $posted, count( $answers ) - 1 ) ];
				++$posted;

				return $answer;
			}
		);
	}

	/**
	 * Reads a fixture and shapes it the way wp_remote_post returns.
	 *
	 * @param string $name The fixture file name.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture on disk, no WP_Filesystem in unit tests.
		$raw     = (string) file_get_contents( __DIR__ . '/../fixtures/telegram/' . $name );
		$fixture = (array) json_decode( $raw, true );

		return array(
			'response' => array( 'code' => (int) $fixture['status'] ),
			'body'     => (string) wp_json_encode( $fixture['body'] ),
		);
	}

	/**
	 * The message goes to the bot's own sendMessage, as HTML, without a preview.
	 */
	public function test_the_message_is_addressed_and_shaped_correctly(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage',
				\Mockery::on(
					function ( array $arguments ): bool {
						$body = json_decode( $arguments['body'], true );

						$this->assertSame( 15, $arguments['timeout'] );
						$this->assertSame( self::CHAT_ID, $body['chat_id'] );
						$this->assertSame( '<b>Dovira</b>', $body['text'] );
						$this->assertSame( 'HTML', $body['parse_mode'] );
						$this->assertTrue( $body['link_preview_options']['is_disabled'] );

						return true;
					}
				)
			)
			->andReturn( $this->fixture( 'send-message-success.written.json' ) );

		TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
	}

	/**
	 * An install without a bot token sends nothing.
	 */
	public function test_a_missing_token_is_refused_without_a_request(): void {
		Functions\when( 'get_option' )->justReturn( array( 'telegram_bot_token' => '' ) );
		Functions\expect( 'wp_remote_post' )->never();

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/No Telegram bot token/' );

		TelegramClient::send_message( self::CHAT_ID, 'anything' );
	}

	/**
	 * An install without a chat id sends nothing either.
	 */
	public function test_a_missing_chat_id_is_refused_without_a_request(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/No Telegram chat id/' );

		TelegramClient::send_message( '', 'anything' );
	}

	/**
	 * Each mapped failure becomes the sentence that names what to fix.
	 *
	 * @param string $fixture The response to answer with.
	 * @param string $expect  A fragment the message must contain.
	 */
	#[DataProvider( 'provide_failures' )]
	public function test_a_refused_message_is_reported_in_words( string $fixture, string $expect ): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( $fixture ) );

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $expect, '/' ) . '/' );

		$this->send();
	}

	/**
	 * The five refusals and the two recorded ones, and what each must say.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_failures(): array {
		return array(
			'the token was revoked'      => array( 'error-unauthorized.json', 'did not accept the bot token' ),
			'the token is mistyped'      => array( 'error-not-found.json', 'does not know a bot with this token' ),
			'the chat id is wrong'       => array( 'error-chat-not-found.written.json', 'cannot find that chat' ),
			'the markup is broken'       => array( 'error-cant-parse-entities.written.json', 'could not read the formatting' ),
			'the bot is not in the chat' => array( 'error-bot-not-in-chat.written.json', 'may not post into this chat' ),
			'the bot is rate-limited'    => array( 'error-too-many-requests.written.json', 'wait 27 seconds' ),
		);
	}

	/**
	 * A flood limit is honoured once, on the spot.
	 */
	public function test_a_flood_limit_is_waited_out_once_and_the_message_goes_again(): void {
		$posted = 0;
		$this->answer_with(
			array(
				$this->fixture( 'error-too-many-requests.written.json' ),
				$this->fixture( 'send-message-success.written.json' ),
			),
			$posted
		);

		$this->send();

		$this->assertSame( 2, $posted, 'the same message is posted again after the wait' );
		$this->assertSame( array( 27 ), $this->waited, 'exactly the seconds Telegram asked for' );
	}

	/**
	 * A second flood limit is the failure it looks like — the wait is not
	 * taken twice.
	 */
	public function test_a_second_flood_limit_ends_the_run(): void {
		$posted = 0;
		$this->answer_with( array( $this->fixture( 'error-too-many-requests.written.json' ) ), $posted );

		try {
			$this->send();
			$this->fail( 'A message Telegram kept refusing must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringContainsString( 'wait 27 seconds', $exception->getMessage() );
		}

		$this->assertSame( 2, $posted, 'two attempts, not a loop' );
		$this->assertSame( array( 27 ), $this->waited );
	}

	/**
	 * A wait longer than the client is willing to sit through is not taken:
	 * that is what the hourly retry is for.
	 */
	public function test_a_wait_beyond_the_cap_is_left_to_the_retry(): void {
		$posted = 0;
		$this->answer_with( array( $this->flood_limit( 300 ) ), $posted );

		try {
			$this->send();
			$this->fail( 'A rate-limited message must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringContainsString( 'wait 300 seconds', $exception->getMessage() );
		}

		$this->assertSame( 1, $posted, 'the message is not posted again' );
		$this->assertSame( array(), $this->waited, 'and nothing is waited out' );
	}

	/**
	 * A flood limit that names no wait is not guessed at.
	 */
	public function test_a_flood_limit_without_a_wait_is_not_repeated(): void {
		$posted = 0;
		$this->answer_with( array( $this->flood_limit( null ) ), $posted );

		try {
			$this->send();
			$this->fail( 'A rate-limited message must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringContainsString( 'Wait a while', $exception->getMessage() );
		}

		$this->assertSame( 1, $posted );
		$this->assertSame( array(), $this->waited );
	}

	/**
	 * Negative check: no other refusal is ever posted twice.
	 */
	public function test_a_refusal_that_is_not_a_flood_limit_is_posted_once(): void {
		$posted = 0;
		$this->answer_with( array( $this->fixture( 'error-unauthorized.json' ) ), $posted );

		try {
			$this->send();
			$this->fail( 'A refused message must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringContainsString( 'did not accept the bot token', $exception->getMessage() );
		}

		$this->assertSame( 1, $posted, 'a revoked token is not worth a second attempt' );
		$this->assertSame( array(), $this->waited );
	}

	/**
	 * Builds a 429 answer that names the given wait, or none at all.
	 *
	 * @param int|null $retry_after The seconds Telegram asks for, or null for an answer without them.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function flood_limit( ?int $retry_after ): array {
		$body = array(
			'ok'          => false,
			'error_code'  => 429,
			'description' => 'Too Many Requests',
		);

		if ( null !== $retry_after ) {
			$body['description'] = 'Too Many Requests: retry after ' . $retry_after;
			$body['parameters']  = array( 'retry_after' => $retry_after );
		}

		return array(
			'response' => array( 'code' => 429 ),
			'body'     => (string) wp_json_encode( $body ),
		);
	}

	/**
	 * Telegram being down is reported as Telegram being down.
	 */
	public function test_a_server_error_is_reported_as_telegrams_side(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 502 ),
				'body'     => '<html><body><h1>502 Bad Gateway</h1></body></html>',
			)
		);

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/not answering right now \(HTTP 502\)/' );

		TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
	}

	/**
	 * A host that cannot reach Telegram says so, with the transport's reason.
	 */
	public function test_an_unreachable_telegram_is_reported_as_such(): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->transport_error( 'cURL error 6: Could not resolve host: api.telegram.org' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/could not reach Telegram.*Could not resolve host/' );

		TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
	}

	/**
	 * A 200 that does not confirm the send is a failure, not a success.
	 */
	public function test_an_answer_that_does_not_confirm_the_send_is_a_failure(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => 'not json at all',
			)
		);

		$this->expectException( TelegramException::class );
		$this->expectExceptionMessageMatches( '/does not understand/' );

		TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
	}

	/**
	 * Negative check: no failure of any kind puts the token into its message.
	 *
	 * The token travels in the URL, so the dangerous case is the one string the
	 * plugin does not write itself — a transport error quoting the request back.
	 *
	 * @param string $fixture The response to answer with.
	 * @param string $expect  The fragment that message is built around.
	 */
	#[DataProvider( 'provide_failures' )]
	public function test_no_refusal_ever_names_the_token( string $fixture, string $expect ): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( $fixture ) );

		try {
			$this->send();
			$this->fail( 'A refused message must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringNotContainsString(
				self::TOKEN,
				$exception->getMessage(),
				'the "' . $expect . '" message carries the bot token'
			);
		}
	}

	/**
	 * Negative check: a transport error that quotes the URL is scrubbed.
	 */
	public function test_a_transport_error_quoting_the_url_loses_the_token(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			$this->transport_error( 'cURL error 28: Operation timed out after 15000 milliseconds for https://api.telegram.org/bot' . self::TOKEN . '/sendMessage' )
		);
		Functions\when( 'is_wp_error' )->justReturn( true );

		try {
			TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
			$this->fail( 'A transport failure must not be reported as a sent message.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringNotContainsString( self::TOKEN, $exception->getMessage() );
			$this->assertStringContainsString( '[bot token]', $exception->getMessage() );
			$this->assertStringContainsString( 'Operation timed out', $exception->getMessage() );
		}
	}

	/**
	 * Negative check: a description echoing the token loses it too.
	 */
	public function test_a_description_echoing_the_token_loses_it(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 400 ),
				'body'     => (string) wp_json_encode(
					array(
						'ok'          => false,
						'error_code'  => 400,
						'description' => 'Bad Request: bot' . self::TOKEN . ' is confused',
					)
				),
			)
		);

		try {
			TelegramClient::send_message( self::CHAT_ID, '<b>Dovira</b>' );
			$this->fail( 'A refused message must not be reported as sent.' );
		} catch ( TelegramException $exception ) {
			$this->assertStringNotContainsString( self::TOKEN, $exception->getMessage() );
		}
	}

	/**
	 * Builds the object wp_remote_post returns when the transport fails.
	 *
	 * @param string $message The transport's own message.
	 */
	private function transport_error( string $message ): object {
		return new class( $message ) {

			/**
			 * The transport's own message.
			 *
			 * @var string
			 */
			private string $message;

			/**
			 * Keeps the message the transport reported.
			 *
			 * @param string $message The transport's own message.
			 */
			public function __construct( string $message ) {
				$this->message = $message;
			}

			/**
			 * Returns the transport's own message.
			 */
			public function get_error_message(): string {
				return $this->message;
			}
		};
	}
}
