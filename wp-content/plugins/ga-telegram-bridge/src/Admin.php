<?php
/**
 * The wp-admin screen the plugin is configured on.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Settings → "GA → Telegram": the Settings API page and its fields.
 *
 * The methods that print a field take every value they render as an argument,
 * so what the administrator sees can be asserted without WordPress or a
 * wp-config.php constant behind it.
 */
final class Admin {

	/**
	 * The page slug the settings sections are registered on.
	 */
	public const PAGE = 'gatb-settings';

	/**
	 * The admin-post action behind the "Check GA" button.
	 */
	public const CHECK_GA_ACTION = 'gatb_check_ga';

	/**
	 * The admin-post action behind the "Check Telegram" button.
	 */
	public const CHECK_TELEGRAM_ACTION = 'gatb_check_telegram';

	/**
	 * Adds the screen under Settings. Hooked on admin_menu.
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'GA → Telegram', 'ga-telegram-bridge' ),
			__( 'GA → Telegram', 'ga-telegram-bridge' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Declares the sections and fields of the screen. Hooked on admin_init.
	 */
	public static function add_fields(): void {
		add_settings_section(
			'gatb_google',
			__( 'Google Analytics', 'ga-telegram-bridge' ),
			array( self::class, 'render_google_section' ),
			self::PAGE
		);

		add_settings_field(
			'gatb_property_id',
			__( 'Property id', 'ga-telegram-bridge' ),
			array( self::class, 'render_field' ),
			self::PAGE,
			'gatb_google',
			array(
				'label_for'   => 'gatb_property_id',
				'key'         => 'property_id',
				'type'        => 'text',
				'class_name'  => 'regular-text',
				'description' => __( 'The numeric id of the GA4 property (Admin → Property settings), not the measurement id.', 'ga-telegram-bridge' ),
			)
		);

		add_settings_field(
			'gatb_service_account_json',
			__( 'Service-account key', 'ga-telegram-bridge' ),
			array( self::class, 'render_secret' ),
			self::PAGE,
			'gatb_google',
			array(
				'label_for'   => 'gatb_service_account_json',
				'key'         => 'service_account_json',
				'multiline'   => true,
				'description' => __( 'The whole JSON key file of the service account, pasted as it is.', 'ga-telegram-bridge' ),
			)
		);

		add_settings_section(
			'gatb_telegram',
			__( 'Telegram', 'ga-telegram-bridge' ),
			array( self::class, 'render_telegram_section' ),
			self::PAGE
		);

		add_settings_field(
			'gatb_telegram_bot_token',
			__( 'Bot token', 'ga-telegram-bridge' ),
			array( self::class, 'render_secret' ),
			self::PAGE,
			'gatb_telegram',
			array(
				'label_for'   => 'gatb_telegram_bot_token',
				'key'         => 'telegram_bot_token',
				'multiline'   => false,
				'description' => __( 'The token BotFather issued for the bot.', 'ga-telegram-bridge' ),
			)
		);

		add_settings_field(
			'gatb_telegram_chat_id',
			__( 'Chat id', 'ga-telegram-bridge' ),
			array( self::class, 'render_field' ),
			self::PAGE,
			'gatb_telegram',
			array(
				'label_for'   => 'gatb_telegram_chat_id',
				'key'         => 'telegram_chat_id',
				'type'        => 'text',
				'class_name'  => 'regular-text',
				'description' => __( 'A number: a person\'s chat id is positive, a group or channel id starts with a minus (for example -1001234567890).', 'ga-telegram-bridge' ),
			)
		);

		add_settings_section(
			'gatb_schedule',
			__( 'Schedule', 'ga-telegram-bridge' ),
			array( self::class, 'render_schedule_section' ),
			self::PAGE
		);

		add_settings_field(
			'gatb_send_time',
			__( 'Send time', 'ga-telegram-bridge' ),
			array( self::class, 'render_field' ),
			self::PAGE,
			'gatb_schedule',
			array(
				'label_for'   => 'gatb_send_time',
				'key'         => 'send_time',
				'type'        => 'time',
				'class_name'  => 'small-text',
				'description' => __( 'Site time, 24-hour.', 'ga-telegram-bridge' ),
			)
		);

		add_settings_field(
			'gatb_max_attempts',
			__( 'Maximum attempts', 'ga-telegram-bridge' ),
			array( self::class, 'render_field' ),
			self::PAGE,
			'gatb_schedule',
			array(
				'label_for'   => 'gatb_max_attempts',
				'key'         => 'max_attempts',
				'type'        => 'number',
				'class_name'  => 'small-text',
				'attributes'  => array(
					'min'  => '1',
					'max'  => '10',
					'step' => '1',
				),
				'description' => __( 'How often a failed report is tried again before a failure notice is sent. Between 1 and 10.', 'ga-telegram-bridge' ),
			)
		);

		add_settings_section(
			'gatb_blocks',
			__( 'Report blocks', 'ga-telegram-bridge' ),
			array( self::class, 'render_blocks_section' ),
			self::PAGE
		);

		add_settings_field(
			'gatb_report_blocks',
			__( 'Blocks', 'ga-telegram-bridge' ),
			array( self::class, 'render_blocks' ),
			self::PAGE,
			'gatb_blocks'
		);
	}

	/**
	 * Prints the screen: the notices, the form and its sections.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Google Analytics → Telegram', 'ga-telegram-bridge' ); ?></h1>
			<?php settings_errors( Settings::OPTION ); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( Settings::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
			<?php self::render_connection_section(); ?>
		</div>
		<?php
	}

	/**
	 * Prints the buttons that talk to Google and to Telegram.
	 *
	 * They cannot live inside the settings form: that one posts to options.php,
	 * and a form cannot contain another. Each check is its own small form to
	 * admin-post.php.
	 */
	public static function render_connection_section(): void {
		?>
		<h2><?php echo esc_html__( 'Connection', 'ga-telegram-bridge' ); ?></h2>
		<p><?php echo esc_html__( 'Save the settings first, then check each side separately.', 'ga-telegram-bridge' ); ?></p>
		<?php
		self::check_form(
			self::CHECK_GA_ACTION,
			__( 'Check GA', 'ga-telegram-bridge' ),
			__( 'Asks Google whether this site can read the configured property. Sends nothing to Telegram.', 'ga-telegram-bridge' )
		);

		self::check_form(
			self::CHECK_TELEGRAM_ACTION,
			__( 'Check Telegram', 'ga-telegram-bridge' ),
			__( 'Really posts a short test message into the configured chat, so you can see it arrive.', 'ga-telegram-bridge' )
		);
	}

	/**
	 * Prints one check button as its own form to admin-post.php.
	 *
	 * @param string $action      The admin-post action it triggers.
	 * @param string $label       The text on the button.
	 * @param string $description What pressing it does.
	 */
	private static function check_form( string $action, string $label, string $description ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<?php
			wp_nonce_field( $action );
			submit_button( $label, 'secondary', 'submit', false );
			?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		</form>
		<?php
	}

	/**
	 * Runs the Google check and sends its outcome back to the screen.
	 *
	 * The result travels as a settings error, the way WordPress carries notices
	 * across a redirect — see redirect_to_settings().
	 */
	public static function handle_check_ga(): void {
		check_admin_referer( self::CHECK_GA_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure this plugin.', 'ga-telegram-bridge' ) );
		}

		try {
			$connection = GaClient::check_connection();

			add_settings_error(
				Settings::OPTION,
				'gatb_check_ga',
				sprintf(
					/* translators: 1: the GA4 property id, 2: the property's reporting time zone. */
					esc_html__( 'Google answered for property %1$s. Its reporting time zone is %2$s — that is the day the report calls "yesterday".', 'ga-telegram-bridge' ),
					esc_html( $connection['property_id'] ),
					esc_html( '' !== $connection['time_zone'] ? $connection['time_zone'] : '-' )
				),
				'success'
			);
		} catch ( GoogleAuthException | GaClientException $exception ) {
			add_settings_error( Settings::OPTION, 'gatb_check_ga', $exception->getMessage(), 'error' );
		}

		self::redirect_to_settings();
	}

	/**
	 * Sends one test message and reports whether Telegram took it.
	 *
	 * Unlike the Google check this one is not a read: it really posts into the
	 * configured chat, which is the only way to prove the bot may write there.
	 */
	public static function handle_check_telegram(): void {
		check_admin_referer( self::CHECK_TELEGRAM_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure this plugin.', 'ga-telegram-bridge' ) );
		}

		$chat_id = Settings::telegram_chat_id();

		try {
			TelegramClient::send_message( $chat_id, self::test_message() );

			add_settings_error(
				Settings::OPTION,
				'gatb_check_telegram',
				sprintf(
					/* translators: %s: the configured Telegram chat id. */
					esc_html__( 'Telegram accepted a test message for chat %s. Open that chat to see it.', 'ga-telegram-bridge' ),
					esc_html( $chat_id )
				),
				'success'
			);
		} catch ( TelegramException $exception ) {
			add_settings_error( Settings::OPTION, 'gatb_check_telegram', $exception->getMessage(), 'error' );
		}

		self::redirect_to_settings();
	}

	/**
	 * Builds the fixed message the Telegram check sends.
	 *
	 * It uses the same HTML parse mode the report will, so a bot that takes
	 * this one can take the report too.
	 */
	public static function test_message(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return sprintf(
			/* translators: %s: the site's host name. */
			__( '📊 <b>%s</b> — test message from the Google Analytics → Telegram bridge. The daily report will arrive in this chat.', 'ga-telegram-bridge' ),
			esc_html( $host )
		);
	}

	/**
	 * Carries the notices of a check back to the settings screen.
	 *
	 * WordPress reads the settings_errors transient only when settings-updated
	 * is set, so the redirect sets it; settings_errors() on the screen then
	 * prints what was registered here.
	 */
	private static function redirect_to_settings(): void {
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE,
					'settings-updated' => 'true',
				),
				admin_url( 'options-general.php' )
			)
		);

		exit;
	}

	/**
	 * Prints the intro of the Google Analytics section.
	 */
	public static function render_google_section(): void {
		self::section_description(
			__( 'Read access to one GA4 property: create a service account in Google Cloud, give its e-mail address Viewer on the property and paste its key file here.', 'ga-telegram-bridge' )
		);
	}

	/**
	 * Prints the intro of the Telegram section.
	 */
	public static function render_telegram_section(): void {
		self::section_description(
			__( 'Where the report is sent. Create a bot with BotFather; to post into a channel, add the bot to it as an administrator.', 'ga-telegram-bridge' )
		);
	}

	/**
	 * Prints the intro of the schedule section.
	 */
	public static function render_schedule_section(): void {
		self::section_description(
			__( 'Kept for the daily sending, which is added in a later release. Nothing runs on a schedule yet.', 'ga-telegram-bridge' )
		);
	}

	/**
	 * Prints the intro of the report blocks section.
	 */
	public static function render_blocks_section(): void {
		self::section_description(
			__( 'What the message contains. The visitors block is always sent.', 'ga-telegram-bridge' )
		);
	}

	/**
	 * Settings API callback of the fields that hold no secret.
	 *
	 * @param array<string, mixed> $args The field arguments given to add_settings_field().
	 */
	public static function render_field( array $args ): void {
		$key = isset( $args['key'] ) ? (string) $args['key'] : '';

		self::text_input(
			$key,
			(string) self::stored_value( $key ),
			isset( $args['type'] ) ? (string) $args['type'] : 'text',
			isset( $args['class_name'] ) ? (string) $args['class_name'] : 'regular-text',
			isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array(),
			isset( $args['description'] ) ? (string) $args['description'] : ''
		);
	}

	/**
	 * Settings API callback of the two fields a constant may take over.
	 *
	 * @param array<string, mixed> $args The field arguments given to add_settings_field().
	 */
	public static function render_secret( array $args ): void {
		$key = isset( $args['key'] ) ? (string) $args['key'] : '';

		self::secret_input(
			$key,
			(string) self::stored_value( $key ),
			Settings::is_secret_locked( $key ),
			! empty( $args['multiline'] ),
			isset( $args['description'] ) ? (string) $args['description'] : ''
		);
	}

	/**
	 * Settings API callback of the report block switches.
	 */
	public static function render_blocks(): void {
		self::block_checkboxes( Settings::blocks() );
	}

	/**
	 * Prints one plain input.
	 *
	 * @param string                $key         The settings key it writes.
	 * @param string                $value       The stored value.
	 * @param string                $type        The input type.
	 * @param string                $class_name  The wp-admin width class.
	 * @param array<string, string> $attributes  Extra attributes, for example min and max.
	 * @param string                $description The text under the field.
	 */
	public static function text_input( string $key, string $value, string $type, string $class_name, array $attributes, string $description ): void {
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo esc_attr( 'gatb_' . $key ); ?>"
			name="<?php echo esc_attr( Settings::OPTION . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="<?php echo esc_attr( $class_name ); ?>"
			<?php
			foreach ( $attributes as $attribute => $attribute_value ) {
				printf( ' %s="%s"', esc_attr( $attribute ), esc_attr( $attribute_value ) );
			}
			?>
		/>
		<?php
		self::field_description( $description );
	}

	/**
	 * Prints one of the two secret fields.
	 *
	 * When the value comes from a constant the field is read-only and empty:
	 * the constant is never printed into the page, and a value submitted for it
	 * is ignored by the sanitizer.
	 *
	 * @param string $key         The settings key it writes.
	 * @param string $value       The stored value.
	 * @param bool   $locked      Whether a wp-config.php constant provides the value.
	 * @param bool   $multiline   Whether the field is a textarea.
	 * @param string $description The text under the field.
	 */
	public static function secret_input( string $key, string $value, bool $locked, bool $multiline, string $description ): void {
		$id    = 'gatb_' . $key;
		$name  = Settings::OPTION . '[' . $key . ']';
		$shown = $locked ? '' : $value;

		if ( $multiline ) {
			?>
			<textarea
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				rows="8"
				class="large-text code"
				autocomplete="off"
				<?php echo $locked ? 'readonly' : ''; ?>
			><?php echo esc_textarea( $shown ); ?></textarea>
			<?php
		} else {
			?>
			<input
				type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $shown ); ?>"
				class="regular-text"
				autocomplete="off"
				<?php echo $locked ? 'readonly' : ''; ?>
			/>
			<?php
		}

		if ( $locked ) {
			printf(
				'<p class="description"><strong>%s</strong> <code>%s</code></p>',
				esc_html__( 'Set in configuration (wp-config.php); the field is ignored.', 'ga-telegram-bridge' ),
				esc_html( (string) ( Settings::SECRET_CONSTANTS[ $key ] ?? '' ) )
			);

			return;
		}

		self::field_description( $description );
	}

	/**
	 * Prints the report block switches.
	 *
	 * Each checkbox is preceded by a hidden field of the same name, so that an
	 * unchecked box arrives as 0 instead of not arriving at all.
	 *
	 * @param array<string, bool> $blocks The stored block switches.
	 */
	public static function block_checkboxes( array $blocks ): void {
		$labels = self::block_labels();

		echo '<fieldset>';

		foreach ( Settings::BLOCKS as $block ) {
			$required = Settings::REQUIRED_BLOCK === $block;
			$enabled  = $required || ! empty( $blocks[ $block ] );
			$id       = 'gatb_block_' . $block;
			$name     = Settings::OPTION . '[blocks][' . $block . ']';

			?>
			<p>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
				<label for="<?php echo esc_attr( $id ); ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="1"
						<?php checked( $enabled ); ?>
						<?php disabled( $required ); ?>
					/>
					<?php echo esc_html( $labels[ $block ] ?? $block ); ?>
					<?php
					if ( $required ) {
						echo ' <span class="description">' . esc_html__( '(always sent)', 'ga-telegram-bridge' ) . '</span>';
					}
					?>
				</label>
			</p>
			<?php
		}

		echo '</fieldset>';
	}

	/**
	 * Returns the label of every report block.
	 *
	 * @return array<string, string>
	 */
	private static function block_labels(): array {
		return array(
			'visitors' => __( 'Visitors', 'ga-telegram-bridge' ),
			'pages'    => __( 'Top pages', 'ga-telegram-bridge' ),
			'channels' => __( 'Traffic sources', 'ga-telegram-bridge' ),
			'cities'   => __( 'Cities', 'ga-telegram-bridge' ),
			'devices'  => __( 'Devices', 'ga-telegram-bridge' ),
		);
	}

	/**
	 * Returns what the option holds for a key.
	 *
	 * @param string $key The settings key.
	 * @return mixed
	 */
	private static function stored_value( string $key ) {
		$settings = Settings::all();

		return $settings[ $key ] ?? '';
	}

	/**
	 * Prints the text under a field.
	 *
	 * @param string $description The text.
	 */
	private static function field_description( string $description ): void {
		if ( '' === $description ) {
			return;
		}

		printf( '<p class="description">%s</p>', esc_html( $description ) );
	}

	/**
	 * Prints the text under a section heading.
	 *
	 * @param string $description The text.
	 */
	private static function section_description( string $description ): void {
		printf( '<p>%s</p>', esc_html( $description ) );
	}
}
