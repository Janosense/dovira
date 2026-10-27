<?php
/**
 * The theme's Telegram bot: its secrets and the notification sent after every form record.
 *
 * The bot token and the join password are constants defined in the install's
 * wp-config.php (DOVIRA_TELEGRAM_BOT_TOKEN, DOVIRA_TELEGRAM_JOIN_PASSWORD);
 * they are never written into the code or the database. So is the one chat
 * thread every notification also goes to (DOVIRA_TELEGRAM_CHAT_ID,
 * DOVIRA_TELEGRAM_THREAD_ID).
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
 * The chat, and its thread (forum topic), that every notification also goes to:
 * DOVIRA_TELEGRAM_CHAT_ID and the optional DOVIRA_TELEGRAM_THREAD_ID. Null when
 * no chat is set, or when a value is malformed (logged by name, never by value).
 *
 * @return array{chat_id: string, message_thread_id?: int}|null
 */
function dovira_telegram_target(): ?array {
	$chat_id = defined( 'DOVIRA_TELEGRAM_CHAT_ID' ) ? trim( (string) constant( 'DOVIRA_TELEGRAM_CHAT_ID' ) ) : '';
	if ( '' === $chat_id ) {
		return null;
	}

	if ( 1 !== preg_match( '/^-?\d+$/', $chat_id ) ) {
		error_log( 'Dovira: DOVIRA_TELEGRAM_CHAT_ID in wp-config.php is not a Telegram chat id, the notification to the thread was not sent.' );

		return null;
	}

	$target    = [ 'chat_id' => $chat_id ];
	$thread_id = defined( 'DOVIRA_TELEGRAM_THREAD_ID' ) ? trim( (string) constant( 'DOVIRA_TELEGRAM_THREAD_ID' ) ) : '';
	if ( '' === $thread_id ) {
		return $target;
	}

	if ( 1 !== preg_match( '/^[1-9]\d*$/', $thread_id ) ) {
		error_log( 'Dovira: DOVIRA_TELEGRAM_THREAD_ID in wp-config.php is not a thread id, the notification to the thread was not sent.' );

		return null;
	}

	$target['message_thread_id'] = (int) $thread_id;

	return $target;
}

/**
 * Sends an HTML message to every subscribed chat and to the thread of
 * dovira_telegram_target(); a subscribed chat that is the target gets it only
 * in the thread. Called only after the form's record is saved; a failed
 * request is ignored, the record stays.
 *
 * @param string $message
 *
 * @return void
 */
function dovira_telegram_notify( string $message ): void {
	$connected_chats = get_option( 'telegram_bot_chats', [] );
	$target          = dovira_telegram_target();
	if ( empty( $connected_chats ) && null === $target ) {
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

	foreach ( (array) $connected_chats as $chat_id ) {
		if ( null !== $target && (string) $chat_id === $target['chat_id'] ) {
			continue;
		}

		$args['body']['chat_id'] = $chat_id;
		wp_remote_get( $url, $args );
	}

	if ( null !== $target ) {
		$args['body'] = array_merge( $args['body'], $target );
		wp_remote_get( $url, $args );
	}
}

/**
 * The Telegram text for a new conversation: which form, which city's site
 * (from the site URL, as the header switcher decides it), and a link to this
 * install's own list of conversations.
 *
 * @param int $form_id The CF7 form id: 1430 is the franchise form, anything else the contact form.
 *
 * @return string
 */
function dovira_conversation_telegram_message( int $form_id ): string {
	$form = ( $form_id === 1430 || $form_id === 1695 ) ? 'з форми заявки на Франшизу' : 'з контактної форми';
	$city = str_contains( get_site_url(), 'kyiv' ) ? 'Києві' : 'Харкові';
	$link = htmlspecialchars( admin_url( 'edit.php?post_type=conversation' ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true );

	return 'Надійшло нове звернення ' . $form . ' із сайту в ' . $city . '. <a href="' . $link . '">Всі звернення тут</a>';
}
