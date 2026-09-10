<?php
/**
 * The plugin's settings: storage, validation and typed access.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Owns the option gatb_settings: its defaults, its sanitizing and its getters.
 *
 * Every rule lives in sanitize_settings(), which touches no WordPress state, so
 * the validation is unit-tested directly. The WordPress-facing sanitize() only
 * gathers the current values and turns the reported errors into admin notices.
 */
final class Settings {

	/**
	 * The option that holds every setting of the plugin (autoload no).
	 */
	public const OPTION = 'gatb_settings';

	/**
	 * The Settings API group the option is registered in.
	 */
	public const GROUP = 'gatb_settings';

	/**
	 * The report blocks, in the order the message prints them.
	 */
	public const BLOCKS = array( 'visitors', 'pages', 'channels', 'cities', 'devices' );

	/**
	 * The block that is always sent and cannot be switched off.
	 */
	public const REQUIRED_BLOCK = 'visitors';

	/**
	 * Settings keys whose value may come from a wp-config.php constant instead.
	 */
	public const SECRET_CONSTANTS = array(
		'service_account_json' => 'GATB_GA_SERVICE_ACCOUNT_JSON',
		'telegram_bot_token'   => 'GATB_TELEGRAM_BOT_TOKEN',
	);

	/**
	 * The keys a service-account key file must carry to be usable.
	 */
	private const SERVICE_ACCOUNT_KEYS = array( 'client_email', 'private_key', 'token_uri' );

	/**
	 * Returns the settings a fresh installation starts with.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'property_id'          => '',
			'service_account_json' => '',
			'telegram_bot_token'   => '',
			'telegram_chat_id'     => '',
			'send_time'            => '09:00',
			'max_attempts'         => 3,
			'blocks'               => array_fill_keys( self::BLOCKS, true ),
		);
	}

	/**
	 * Registers the option with the Settings API. Hooked on admin_init.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Returns the stored settings, completed with the defaults and typed.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return self::merge_defaults( get_option( self::OPTION, array() ) );
	}

	/**
	 * Completes a stored value with the defaults and forces every type.
	 *
	 * A partially written option can therefore never produce a missing key or a
	 * value of the wrong type in a getter.
	 *
	 * @param mixed $stored The raw option value.
	 * @return array<string, mixed>
	 */
	public static function merge_defaults( $stored ): array {
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();

		return array(
			'property_id'          => self::string_value( $stored, 'property_id', $defaults ),
			'service_account_json' => self::string_value( $stored, 'service_account_json', $defaults ),
			'telegram_bot_token'   => self::string_value( $stored, 'telegram_bot_token', $defaults ),
			'telegram_chat_id'     => self::string_value( $stored, 'telegram_chat_id', $defaults ),
			'send_time'            => self::string_value( $stored, 'send_time', $defaults ),
			'max_attempts'         => self::int_value( $stored, 'max_attempts', $defaults ),
			'blocks'               => self::merge_blocks( $stored['blocks'] ?? array() ),
		);
	}

	/**
	 * Completes the block switches: every known block, visitors always on.
	 *
	 * @param mixed $stored The stored or submitted block switches.
	 * @return array<string, bool>
	 */
	public static function merge_blocks( $stored ): array {
		$stored = is_array( $stored ) ? $stored : array();
		$blocks = array();

		foreach ( self::BLOCKS as $block ) {
			$blocks[ $block ] = ! empty( $stored[ $block ] );
		}

		$blocks[ self::REQUIRED_BLOCK ] = true;

		return $blocks;
	}

	/**
	 * Sanitize callback of the option: validates and reports what it rejected.
	 *
	 * @param mixed $raw The submitted option value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $raw ): array {
		$result = self::sanitize_settings(
			is_array( $raw ) ? $raw : array(),
			self::all(),
			self::locked_keys()
		);

		foreach ( $result['errors'] as $field => $message ) {
			add_settings_error( self::OPTION, 'gatb_' . $field, $message, 'error' );
		}

		return $result['values'];
	}

	/**
	 * Validates a submission field by field against the currently stored values.
	 *
	 * An empty value is accepted: an install that is not configured yet must be
	 * able to save a half-filled form. A value that is present but breaks its
	 * rule is rejected alone — that field keeps what is stored, every other
	 * field of the same submission is saved, and the reason is reported. A key
	 * listed in $locked comes from a constant: whatever was submitted for it is
	 * ignored without an error.
	 *
	 * @param array<string, mixed> $raw     The submitted values.
	 * @param array<string, mixed> $current The values stored right now.
	 * @param array<int, string>   $locked  Keys whose value comes from a constant.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public static function sanitize_settings( array $raw, array $current, array $locked = array() ): array {
		$values = self::merge_defaults( $current );
		$errors = array();

		$property_id = self::submitted( $raw, 'property_id' );

		if ( null !== $property_id ) {
			if ( '' === $property_id || 1 === preg_match( '/^\d+$/', $property_id ) ) {
				$values['property_id'] = $property_id;
			} else {
				$errors['property_id'] = __(
					'The GA4 property id must consist of digits only (for example 123456789). The previous value was kept.',
					'ga-telegram-bridge'
				);
			}
		}

		$json = in_array( 'service_account_json', $locked, true )
			? null
			: self::submitted( $raw, 'service_account_json' );

		if ( null !== $json ) {
			if ( '' === $json || self::is_service_account_json( $json ) ) {
				$values['service_account_json'] = $json;
			} else {
				$errors['service_account_json'] = __(
					'The service-account key must be the JSON file Google generated, containing client_email, private_key and token_uri. The previous key was kept.',
					'ga-telegram-bridge'
				);
			}
		}

		$token = in_array( 'telegram_bot_token', $locked, true )
			? null
			: self::submitted( $raw, 'telegram_bot_token' );

		if ( null !== $token ) {
			$values['telegram_bot_token'] = $token;
		}

		$chat_id = self::submitted( $raw, 'telegram_chat_id' );

		if ( null !== $chat_id ) {
			if ( '' === $chat_id || 1 === preg_match( '/^-?\d+$/', $chat_id ) ) {
				$values['telegram_chat_id'] = $chat_id;
			} else {
				$errors['telegram_chat_id'] = __(
					'The chat id must be a number, with a leading minus for groups and channels (for example -1001234567890). The previous value was kept.',
					'ga-telegram-bridge'
				);
			}
		}

		$send_time = self::submitted( $raw, 'send_time' );

		if ( null !== $send_time && '' !== $send_time ) {
			if ( 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $send_time ) ) {
				$values['send_time'] = $send_time;
			} else {
				$errors['send_time'] = __(
					'The send time must be given as HH:MM in 24-hour form (for example 09:00). The previous value was kept.',
					'ga-telegram-bridge'
				);
			}
		}

		$max_attempts = self::submitted( $raw, 'max_attempts' );

		if ( null !== $max_attempts && '' !== $max_attempts ) {
			if ( 1 === preg_match( '/^\d+$/', $max_attempts ) && (int) $max_attempts >= 1 && (int) $max_attempts <= 10 ) {
				$values['max_attempts'] = (int) $max_attempts;
			} else {
				$errors['max_attempts'] = __(
					'The number of attempts must be a whole number between 1 and 10. The previous value was kept.',
					'ga-telegram-bridge'
				);
			}
		}

		if ( array_key_exists( 'blocks', $raw ) ) {
			$values['blocks'] = self::merge_blocks( $raw['blocks'] );
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Returns the GA4 property the report is read from.
	 */
	public static function property_id(): string {
		return (string) self::all()['property_id'];
	}

	/**
	 * Returns the service-account key: the constant wins over the option.
	 */
	public static function service_account_json(): string {
		return self::secret(
			self::SECRET_CONSTANTS['service_account_json'],
			(string) self::all()['service_account_json']
		);
	}

	/**
	 * Returns the Telegram bot token: the constant wins over the option.
	 */
	public static function telegram_bot_token(): string {
		return self::secret(
			self::SECRET_CONSTANTS['telegram_bot_token'],
			(string) self::all()['telegram_bot_token']
		);
	}

	/**
	 * Returns the chat the report is sent to.
	 */
	public static function telegram_chat_id(): string {
		return (string) self::all()['telegram_chat_id'];
	}

	/**
	 * Returns the site-local time of day the report goes out at (HH:MM).
	 */
	public static function send_time(): string {
		return (string) self::all()['send_time'];
	}

	/**
	 * Returns how many times a failing run is retried.
	 */
	public static function max_attempts(): int {
		return (int) self::all()['max_attempts'];
	}

	/**
	 * Returns every block switch, visitors always on.
	 *
	 * @return array<string, bool>
	 */
	public static function blocks(): array {
		return self::merge_blocks( self::all()['blocks'] );
	}

	/**
	 * Tells whether a report block is part of the message.
	 *
	 * @param string $block The block key.
	 */
	public static function is_block_enabled( string $block ): bool {
		$blocks = self::blocks();

		return isset( $blocks[ $block ] ) && $blocks[ $block ];
	}

	/**
	 * Tells whether a secret comes from a constant instead of the option.
	 *
	 * @param string $key The settings key.
	 */
	public static function is_secret_locked( string $key ): bool {
		return isset( self::SECRET_CONSTANTS[ $key ] ) && defined( self::SECRET_CONSTANTS[ $key ] );
	}

	/**
	 * Returns the settings keys whose value comes from a constant.
	 *
	 * @return array<int, string>
	 */
	public static function locked_keys(): array {
		$locked = array();

		foreach ( array_keys( self::SECRET_CONSTANTS ) as $key ) {
			if ( self::is_secret_locked( $key ) ) {
				$locked[] = $key;
			}
		}

		return $locked;
	}

	/**
	 * Returns the constant's value when it is defined, the stored one otherwise.
	 *
	 * The constant name is a parameter so that both branches can be tested
	 * without defining the plugin's real constants in the test process.
	 *
	 * @param string $constant_name The wp-config.php constant to read.
	 * @param string $stored        The value stored in the option.
	 */
	public static function secret( string $constant_name, string $stored ): string {
		if ( ! defined( $constant_name ) ) {
			return $stored;
		}

		$value = constant( $constant_name );

		return is_string( $value ) ? $value : $stored;
	}

	/**
	 * Tells whether a JSON string is a usable service-account key file.
	 *
	 * @param string $json The submitted key file contents.
	 */
	private static function is_service_account_json( string $json ): bool {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		foreach ( self::SERVICE_ACCOUNT_KEYS as $key ) {
			if ( ! isset( $decoded[ $key ] ) || ! is_string( $decoded[ $key ] ) || '' === trim( $decoded[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a submitted scalar as a trimmed string, or null when not submitted.
	 *
	 * @param array<string, mixed> $raw The submitted values.
	 * @param string               $key The key to read.
	 */
	private static function submitted( array $raw, string $key ): ?string {
		if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) ) {
			return null;
		}

		return trim( (string) $raw[ $key ] );
	}

	/**
	 * Reads a string from a stored value, falling back to the default.
	 *
	 * @param array<string, mixed> $stored   The stored option.
	 * @param string               $key      The key to read.
	 * @param array<string, mixed> $defaults The default settings.
	 */
	private static function string_value( array $stored, string $key, array $defaults ): string {
		if ( isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ) {
			return (string) $stored[ $key ];
		}

		return (string) $defaults[ $key ];
	}

	/**
	 * Reads an integer from a stored value, falling back to the default.
	 *
	 * @param array<string, mixed> $stored   The stored option.
	 * @param string               $key      The key to read.
	 * @param array<string, mixed> $defaults The default settings.
	 */
	private static function int_value( array $stored, string $key, array $defaults ): int {
		if ( isset( $stored[ $key ] ) && is_numeric( $stored[ $key ] ) ) {
			return (int) $stored[ $key ];
		}

		return (int) $defaults[ $key ];
	}
}
