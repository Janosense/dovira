<?php

namespace dovira;

use JsonException;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class TelegramController extends WP_REST_Controller {

	private string $access_token = '7768117564:AAFS45iz5R_-VKnFGj5WzaIAHUtiXfdbiTs';

	public function __construct() {
		$this->namespace = 'dovira/v1';
		$this->rest_base = 'telegram';
	}

	/**
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, "/$this->rest_base/handle-updates/", [
			'callback'            => [ $this, 'handle_updates' ],
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, "/$this->rest_base/send-test-message/", [
			'callback'            => [ $this, 'send_test_message' ],
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, "/$this->rest_base/reset-telegram-log/", [
			'callback'            => [ $this, 'reset_telegram_log' ],
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	public function handle_updates( WP_REST_Request $request ): void {
		$params                  = $request->get_params();
		$telegram_webhook_data   = get_option( 'telegram_webhook_data', [] );
		$telegram_webhook_data[] = $params;
		update_option( 'telegram_webhook_data', $telegram_webhook_data );

		if ( isset( $params['message'] ) && isset( $params['message']['text'] ) && ! empty( $params['message']['text'] ) ) {
			switch ( $params['message']['text'] ) {
				case '/start':
				case '/join':
					$connected_chats = get_option( 'telegram_bot_chats', [] );

					if ( in_array( $params['message']['chat']['id'], $connected_chats ) ) {
						$message = 'Вас успішно підключено. Ви отримаєте повідомлення як тільки хтось відправить форму зворотнього звʼязку.';
					} else {
						$message = 'Для підключення до бота, будь ласка, введіть пароль:';
					}

					$url = 'https://api.telegram.org/bot' . $this->access_token . '/sendMessage';

					$args = [
						'timeout'     => 45,
						'redirection' => 5,
						'body'        => [
							'chat_id' => $params['message']['chat']['id'],
							'text'    => $message
						],
					];

					$response = wp_remote_get( $url, $args );
					break;
				case '/reset':
					$connected_chats = get_option( 'telegram_bot_chats', [] );
					if ( isset( $connected_chats[ $params['message']['chat']['id'] ] ) ) {
						unset( $connected_chats[ $params['message']['chat']['id'] ] );
						update_option( 'telegram_bot_chats', $connected_chats );

						$url  = 'https://api.telegram.org/bot' . $this->access_token . '/sendMessage';
						$args = [
							'timeout'     => 45,
							'redirection' => 5,
							'body'        => [
								'chat_id' => $params['message']['chat']['id'],
								'text'    => 'Дані успішно скинуто.'
							],
						];

						$response = wp_remote_get( $url, $args );
					}
					break;
				default:
					if ($params['message']['text'] === '12340987') {
						$connected_chats = get_option( 'telegram_bot_chats', [] );
						$connected_chats[$params['message']['chat']['id']] = $params['message']['chat']['id'];

						update_option( 'telegram_bot_chats', $connected_chats );


						$url  = 'https://api.telegram.org/bot' . $this->access_token . '/sendMessage';
						$args = [
							'timeout'     => 45,
							'redirection' => 5,
							'body'        => [
								'chat_id' => $params['message']['chat']['id'],
								'text'    => 'Вас успішно підключено. Ви отримаєте повідомлення як тільки хтось відправить форму зворотнього звʼязку.'
							],
						];

						$response = wp_remote_get( $url, $args );
					}
					break;
			}
		}

		wp_send_json( [], 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	public function send_test_message( WP_REST_Request $request ): void {
		$message = 'Ох, ні! <strong>Тимофій Синянський</strong> шойно випередив тебе загальному заліку' . "\n";
		$message .= " та заліку Хлопців!\n";
		$message .= 'Твоя позиція: <strong>12</strong>';
		$message .= 'Загальний залік <a href="https://irpin.run/events/struggle-2025/results/">тут</a>.';
		$message .= '<strong>Тимофій</strong>, треба бігти!)';

		$url  = 'https://api.telegram.org/bot' . $this->access_token . '/sendMessage';
		$args = [
			'timeout'     => 45,
			'redirection' => 5,
			'body'        => [
				'chat_id'              => 443244028,
				'text'                 => $message,
				'parse_mode'           => 'html',
				'link_preview_options' => json_encode( [
					'is_disabled' => true,
				], JSON_THROW_ON_ERROR ),
			],
		];
		wp_remote_get( $url, $args );

		wp_send_json( [], 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	public function reset_telegram_log( WP_REST_Request $request ): void {
		update_option( 'telegram_webhook_data', [] );
		wp_send_json( [], 200 );
	}
}

