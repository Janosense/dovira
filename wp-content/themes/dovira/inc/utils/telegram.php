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
 * Sends an HTML message to every subscribed chat. Called only after the form's
 * record is saved; a failed request is ignored, the record stays.
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

	foreach ( $connected_chats as $chat_id ) {
		$args['body']['chat_id'] = $chat_id;
		wp_remote_get( $url, $args );
	}
}
