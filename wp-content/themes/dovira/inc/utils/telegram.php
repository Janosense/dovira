<?php
/**
 * The theme's Telegram bot: its secrets and the notification sent after every form record.
 *
 * The bot token and the join password are constants defined in the install's
 * wp-config.php (DOVIRA_TELEGRAM_BOT_TOKEN, DOVIRA_TELEGRAM_JOIN_PASSWORD);
 * they are never written into the code or the database.
 */

/**
 * The bot token, or '' when DOVIRA_TELEGRAM_BOT_TOKEN is not defined.
 *
 * @return string
 */
function dovira_telegram_token(): string {
	return defined( 'DOVIRA_TELEGRAM_BOT_TOKEN' ) ? (string) constant( 'DOVIRA_TELEGRAM_BOT_TOKEN' ) : '';
}

/**
 * The password a chat sends to the bot to subscribe, or '' when DOVIRA_TELEGRAM_JOIN_PASSWORD is not defined.
 *
 * @return string
 */
function dovira_telegram_join_password(): string {
	return defined( 'DOVIRA_TELEGRAM_JOIN_PASSWORD' ) ? (string) constant( 'DOVIRA_TELEGRAM_JOIN_PASSWORD' ) : '';
}

/**
 * Whether a message text is the join password. An unset password never matches.
 *
 * @param string $text
 *
 * @return bool
 */
function dovira_telegram_is_join_password( string $text ): bool {
	$password = dovira_telegram_join_password();

	return '' !== $password && hash_equals( $password, $text );
}

/**
 * The Bot API URL of one method, e.g. sendMessage. Carries the token: never log it.
 *
 * @param string $method
 *
 * @return string
 */
function dovira_telegram_api_url( string $method ): string {
	return 'https://api.telegram.org/bot' . dovira_telegram_token() . '/' . $method;
}

/**
 * The forum topic (thread) an incoming message was written in, or null for a
 * chat's main area. Only a topic counts: in an ordinary group a reply also
 * carries message_thread_id, without is_topic_message.
 *
 * @param array $message The update's `message`.
 *
 * @return int|null
 */
function dovira_telegram_thread_of( array $message ): ?int {
	if ( empty( $message['is_topic_message'] ) || empty( $message['message_thread_id'] ) ) {
		return null;
	}

	return (int) $message['message_thread_id'];
}

/**
 * The sendMessage body of a reply to an incoming message: the same chat, and the same topic if it came from one.
 *
 * @param array  $message The update's `message`.
 * @param string $text
 *
 * @return array
 */
function dovira_telegram_reply_body( array $message, string $text ): array {
	$body = [
		'chat_id' => $message['chat']['id'],
		'text'    => $text,
	];

	$thread_id = dovira_telegram_thread_of( $message );
	if ( null !== $thread_id ) {
		$body['message_thread_id'] = $thread_id;
	}

	return $body;
}

/**
 * Subscribes a chat to the notifications: into its topic when a thread is given,
 * otherwise into the chat itself (a thread stored earlier is forgotten).
 *
 * @param int|string $chat_id
 * @param int|null   $thread_id
 *
 * @return void
 */
function dovira_telegram_subscribe( $chat_id, ?int $thread_id ): void {
	$connected_chats             = get_option( 'telegram_bot_chats', [] );
	$connected_chats[ $chat_id ] = $chat_id;
	update_option( 'telegram_bot_chats', $connected_chats );

	$threads = get_option( 'telegram_bot_chat_threads', [] );
	if ( null !== $thread_id ) {
		$threads[ $chat_id ] = $thread_id;
	} else {
		unset( $threads[ $chat_id ] );
	}
	update_option( 'telegram_bot_chat_threads', $threads );
}

/**
 * Removes a chat, and its thread, from the notifications.
 *
 * @param int|string $chat_id
 *
 * @return void
 */
function dovira_telegram_unsubscribe( $chat_id ): void {
	$connected_chats = get_option( 'telegram_bot_chats', [] );
	unset( $connected_chats[ $chat_id ] );
	update_option( 'telegram_bot_chats', $connected_chats );

	$threads = get_option( 'telegram_bot_chat_threads', [] );
	unset( $threads[ $chat_id ] );
	update_option( 'telegram_bot_chat_threads', $threads );
}

/**
 * Sends an HTML message to every subscribed chat, into the chat's topic when it
 * subscribed from one. Called only after the form's record is saved; a failed
 * request is ignored, the record stays.
 *
 * @param string $message
 *
 * @return void
 */
function dovira_telegram_notify( string $message ): void {
	$connected_chats = get_option( 'telegram_bot_chats', [] );
	if ( empty( $connected_chats ) ) {
		return;
	}

	if ( '' === dovira_telegram_token() ) {
		error_log( 'Dovira: DOVIRA_TELEGRAM_BOT_TOKEN is not defined in wp-config.php, the Telegram notification was not sent.' );

		return;
	}

	$url  = dovira_telegram_api_url( 'sendMessage' );
	$args = [
		'timeout'     => 45,
		'redirection' => 5,
		'body'        => [
			'text'                 => $message,
			'parse_mode'           => 'html',
			'link_preview_options' => json_encode( [
				'is_disabled' => true,
			], JSON_THROW_ON_ERROR ),
		],
	];

	$threads = get_option( 'telegram_bot_chat_threads', [] );

	foreach ( $connected_chats as $chat_id ) {
		$args['body']['chat_id'] = $chat_id;
		if ( ! empty( $threads[ $chat_id ] ) ) {
			$args['body']['message_thread_id'] = (int) $threads[ $chat_id ];
		} else {
			unset( $args['body']['message_thread_id'] );
		}
		wp_remote_get( $url, $args );
	}
}
