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
		</div>
		<?php
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
