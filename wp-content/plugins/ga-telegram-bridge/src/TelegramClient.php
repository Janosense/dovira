<?php
/**
 * The minimal Telegram Bot API client.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Sends one message to one chat, with one readable sentence per way it can
 * fail.
 *
 * The bot token travels in the request URL, so every value that reaches an
 * exception message passes through without_token() first: Telegram never puts
 * the token in its own answers, but a transport error may quote the URL back.
 */
final class TelegramClient {

	/**
	 * The Bot API this client speaks to.
	 */
	private const ENDPOINT = 'https://api.telegram.org';

	/**
	 * How long to wait for Telegram to accept a message.
	 */
	private const TIMEOUT = 15;

	/**
	 * What a redacted token is replaced with.
	 */
	private const REDACTED = '[bot token]';

	/**
	 * The longest pause this client takes inside one run, in seconds.
	 *
	 * Telegram answers a flood limit with the number of seconds to wait, and a
	 * short one is worth honouring on the spot. A long one is not: every message
	 * is sent from a WP-Cron or an admin request, and both run under the host's
	 * own request timeout — that is what the hourly retry is for.
	 */
	private const MAX_WAIT = 30;

	/**
	 * Sends one HTML message to one chat.
	 *
	 * A flood limit is the one refusal answered inside the same run: Telegram
	 * says how long to wait, and a wait it asks for is honoured once. Anything
	 * else — a second refusal included — is the failure it looks like.
	 *
	 * @param string        $chat_id The chat to post into: positive for a person, negative for a group or channel.
	 * @param string        $html    The message, in Telegram's HTML parse mode.
	 * @param callable|null $wait    How to wait between the two attempts; injected by the tests, sleep() otherwise.
	 * @throws TelegramException When the plugin is unconfigured, or Telegram refuses.
	 */
	public static function send_message( string $chat_id, string $html, ?callable $wait = null ): void {
		$token = Settings::telegram_bot_token();

		if ( '' === trim( $token ) ) {
			throw new TelegramException(
				esc_html__( 'No Telegram bot token is configured yet.', 'ga-telegram-bridge' )
			);
		}

		if ( '' === trim( $chat_id ) ) {
			throw new TelegramException(
				esc_html__( 'No Telegram chat id is configured yet.', 'ga-telegram-bridge' )
			);
		}

		$response = self::post( $token, $chat_id, $html );
		$seconds  = self::flood_wait( $response );

		if ( $seconds > 0 ) {
			if ( null === $wait ) {
				sleep( $seconds );
			} else {
				$wait( $seconds );
			}

			$response = self::post( $token, $chat_id, $html );
		}

		self::accept( $response, $token );
	}

	/**
	 * Posts the message once.
	 *
	 * @param string $token   The bot token.
	 * @param string $chat_id The chat to post into.
	 * @param string $html    The message.
	 * @return array<string, mixed>|\WP_Error What wp_remote_post() answered.
	 */
	private static function post( string $token, string $chat_id, string $html ) {
		return wp_remote_post(
			self::ENDPOINT . '/bot' . $token . '/sendMessage',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => (string) wp_json_encode(
					array(
						'chat_id'              => $chat_id,
						'text'                 => $html,
						'parse_mode'           => 'HTML',
						// The field the sprint named, disable_web_page_preview, was
						// dropped from the Bot API documentation; this is its
						// replacement and the API is versioned on Telegram's side.
						'link_preview_options' => array( 'is_disabled' => true ),
					)
				),
			)
		);
	}

	/**
	 * Returns how long Telegram asked to wait before one more attempt.
	 *
	 * Zero for every answer that is not a flood limit naming a wait this client
	 * is willing to sit through — including one it names but that is too long.
	 *
	 * @param array<string, mixed>|\WP_Error $response What wp_remote_post() answered.
	 */
	private static function flood_wait( $response ): int {
		if ( is_wp_error( $response ) || 429 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return 0;
		}

		$body       = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$parameters = is_array( $body ) && isset( $body['parameters'] ) && is_array( $body['parameters'] )
			? $body['parameters']
			: array();
		$seconds    = isset( $parameters['retry_after'] ) && is_numeric( $parameters['retry_after'] )
			? (int) $parameters['retry_after']
			: 0;

		return ( $seconds > 0 && $seconds <= self::MAX_WAIT ) ? $seconds : 0;
	}

	/**
	 * Returns quietly when Telegram accepted the message, and throws otherwise.
	 *
	 * @param array<string, mixed>|\WP_Error $response What wp_remote_post() answered.
	 * @param string                         $token    The bot token, so it can be kept out of every message.
	 * @throws TelegramException When the site could not reach Telegram, or Telegram refused.
	 */
	private static function accept( $response, string $token ): void {
		if ( is_wp_error( $response ) ) {
			throw new TelegramException(
				sprintf(
					/* translators: %s: the reason the HTTP request failed. */
					esc_html__( 'This site could not reach Telegram: %s', 'ga-telegram-bridge' ),
					esc_html( self::without_token( $response->get_error_message(), $token ) )
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- client_error_message() escapes every value it takes from Telegram.
			throw new TelegramException( self::client_error_message( $status, $body, $token ) );
		}

		if ( ! is_array( $body ) || true !== ( $body['ok'] ?? false ) ) {
			throw new TelegramException(
				esc_html__( 'Telegram answered in a way this plugin does not understand. The message may or may not have been sent.', 'ga-telegram-bridge' )
			);
		}
	}

	/**
	 * Turns a refused call into a sentence about what to fix.
	 *
	 * Telegram answers `{"ok": false, "error_code": …, "description": "…"}`,
	 * with an optional `parameters` object carrying retry_after.
	 *
	 * @param int    $status The HTTP status.
	 * @param mixed  $body   The decoded response body.
	 * @param string $token  The bot token, so it can be kept out of the result.
	 */
	public static function client_error_message( int $status, $body, string $token = '' ): string {
		$decoded     = is_array( $body ) ? $body : array();
		$description = isset( $decoded['description'] ) && is_string( $decoded['description'] )
			? self::without_token( $decoded['description'], $token )
			: '';
		$parameters  = isset( $decoded['parameters'] ) && is_array( $decoded['parameters'] )
			? $decoded['parameters']
			: array();

		if ( 401 === $status ) {
			return esc_html__( 'Telegram did not accept the bot token. Check the token BotFather issued for this bot — a token is revoked as soon as a new one is generated.', 'ga-telegram-bridge' );
		}

		if ( 404 === $status ) {
			return esc_html__( 'Telegram does not know a bot with this token. Copy the whole token from BotFather, including the digits before the colon.', 'ga-telegram-bridge' );
		}

		if ( 429 === $status ) {
			$retry_after = isset( $parameters['retry_after'] ) && is_numeric( $parameters['retry_after'] )
				? (int) $parameters['retry_after']
				: 0;

			if ( $retry_after > 0 ) {
				return sprintf(
					/* translators: %d: how many seconds Telegram asks to wait. */
					esc_html__( 'Telegram is rate-limiting this bot and asks to wait %d seconds before trying again.', 'ga-telegram-bridge' ),
					$retry_after
				);
			}

			return esc_html__( 'Telegram is rate-limiting this bot. Wait a while before trying again.', 'ga-telegram-bridge' );
		}

		if ( 403 === $status ) {
			return sprintf(
				/* translators: %s: the reason Telegram gave. */
				esc_html__( 'The bot may not post into this chat: %s To post into a channel, add the bot to it as an administrator; a person has to write to the bot once before it may answer.', 'ga-telegram-bridge' ),
				esc_html( $description )
			);
		}

		if ( 400 === $status && false !== strpos( $description, 'chat not found' ) ) {
			return esc_html__( 'Telegram cannot find that chat. Check the chat id — a channel id begins with -100 — and make sure the bot has been added to it.', 'ga-telegram-bridge' );
		}

		if ( 400 === $status && false !== strpos( $description, 'parse entities' ) ) {
			return sprintf(
				/* translators: %s: the reason Telegram gave. */
				esc_html__( 'Telegram could not read the formatting of the message: %s This is a fault in the message, not in the settings.', 'ga-telegram-bridge' ),
				esc_html( $description )
			);
		}

		if ( $status >= 500 ) {
			return sprintf(
				/* translators: %d: the HTTP status Telegram answered with. */
				esc_html__( 'Telegram is not answering right now (HTTP %d). This is on Telegram\'s side; try again later.', 'ga-telegram-bridge' ),
				$status
			);
		}

		if ( '' === $description ) {
			return sprintf(
				/* translators: %d: the HTTP status Telegram answered with. */
				esc_html__( 'Telegram refused the message (HTTP %d) and gave no reason.', 'ga-telegram-bridge' ),
				$status
			);
		}

		return sprintf(
			/* translators: 1: the HTTP status Telegram answered with, 2: the reason Telegram gave. */
			esc_html__( 'Telegram refused the message (HTTP %1$d): %2$s', 'ga-telegram-bridge' ),
			$status,
			esc_html( $description )
		);
	}

	/**
	 * Removes the bot token from a string that is about to be shown or logged.
	 *
	 * @param string $text  The text to clean.
	 * @param string $token The bot token.
	 */
	private static function without_token( string $text, string $token ): string {
		if ( '' === $token ) {
			return $text;
		}

		return str_replace( $token, self::REDACTED, $text );
	}
}
