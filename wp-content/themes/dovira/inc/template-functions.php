<?php

if ( ! function_exists( 'starter_theme_setup' ) ) {
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	function starter_theme_setup(): void {
		/*
         * Make theme available for translation.
         * Translations can be filed in the /languages/ directory.
         */
		load_theme_textdomain( 'dovira', get_template_directory() . '/languages' );

		/*
		 * Let WordPress manage the document title.
		 * This theme does not use a hard-coded <title> tag in the document head,
		 * WordPress will provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/**
		 * Add post-formats support.
		 */
		add_theme_support(
			'post-formats',
			[
				'link',
				'aside',
				'gallery',
				'image',
				'quote',
				'status',
				'video',
				'audio',
				'chat',
			]
		);

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );

		/*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
		add_theme_support(
			'html5',
			[
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
				'navigation-widgets',
			]
		);

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for custom line height controls.
		add_theme_support( 'custom-line-height' );

		// Add support for experimental link color control.
		add_theme_support( 'experimental-link-color' );

		// Add support for experimental cover block spacing.
		add_theme_support( 'custom-spacing' );

		add_theme_support( 'editor-color-palette', array(
			array(
				'name'  => __( 'Cyan', 'cargill' ),
				'slug'  => "color-cyan",
				'color' => '#1995AD',
			)
		) );

		/**
		 * Register custom nav menus
		 */
		register_nav_menus(
			[
				'primary'      => esc_html__( 'Primary menu', 'dovira' ),
				'footer_col_1' => esc_html__( 'Footer menu Column 1', 'dovira' ),
				'footer_col_2' => esc_html__( 'Footer menu Column 2', 'dovira' ),
			]
		);

	}
}
add_action( 'after_setup_theme', 'starter_theme_setup', 1 );


/**
 * Insert hmr into head for live reload
 *
 * @return void
 */
function starter_theme_vite_head_module(): void {
	if ( wp_get_environment_type() === 'development' ) {
		echo '<script type="module" src="' . VITE_SERVER . VITE_ENTRY_POINT . '"></script>';
	}
}

add_action( 'wp_head', 'starter_theme_vite_head_module' );

/**
 * Insert hmr into admin head for live reload
 * Important! This will reload the entire admin page on style/script change. Use only when styling admin pages, do not use when editing content.
 *
 * @return void
 */
function starter_theme_vite_admin_head_module(): void {
	if ( wp_get_environment_type() === 'development' ) {
		echo '<script type="module" src="' . VITE_SERVER . '/source/admin.js' . '"></script>';
	}
}

add_action( 'admin_head', 'starter_theme_vite_admin_head_module' );


/**
 * Enqueue scripts and styles.
 *
 * @return void
 * @throws JsonException
 * @since 1.0.0
 *
 */
function starter_theme_scripts(): void {
	if ( wp_get_environment_type() !== 'development' ) {
		$theme_version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style( 'dovira-styles', starter_theme_vite_asset( 'source/main.css' ), [], $theme_version );
		wp_enqueue_script( 'dovira-scripts', starter_theme_vite_asset( 'source/main.js' ), [], $theme_version, true );
	}

	/**
	 * Remove default WP styles
	 */
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'classic-theme-styles' );
}

add_action( 'wp_enqueue_scripts', 'starter_theme_scripts' );

/**
 * Enqueue admin scripts and styles.
 *
 * @return void
 * @throws JsonException
 * @since 1.0.0
 */
function starter_theme_admin_scripts(): void {
	if ( wp_get_environment_type() !== 'development' ) {
		$theme_version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style( 'dovira-admin-styles', starter_theme_vite_asset( 'source/admin.css' ), [], $theme_version );
		wp_enqueue_script( 'dovira-admin-scripts', starter_theme_vite_asset( 'source/admin.js' ), [], $theme_version, true );
	}
}

add_action( 'admin_enqueue_scripts', 'starter_theme_admin_scripts' );

/**
 * Defer loading of JavaScript assets
 *
 * @param $tag
 * @param $handle
 *
 * @return array|mixed|string|string[]
 */
function starter_theme_defer_scripts( $tag, $handle ): mixed {
	$excluded = [
		'jquery-core',
		'jquery-migrate',
	];

	if ( is_admin() || starter_theme_is_login_page() || in_array( $handle, $excluded, true ) ) {
		return $tag;
	}

	return str_replace( 'src', 'defer src', $tag );
}

add_filter( 'script_loader_tag', 'starter_theme_defer_scripts', 10, 2 );

/**
 * Add "is-IE" class to body if the user is on Internet Explorer.
 *
 * @return void
 * @since 1.0.0
 *
 */
function starter_theme_add_ie_class(): void {
	?>
	<script>
		if (-1 !== navigator.userAgent.indexOf('MSIE') || -1 !== navigator.appVersion.indexOf('Trident/')) {
			document.body.classList.add('is-IE');
		}
	</script>
	<?php
}

add_action( 'wp_footer', 'starter_theme_add_ie_class' );

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 *
 * @return array
 * @since 1.0.0
 *
 */
function starter_theme_body_classes( array $classes ): array {

	// Helps detect if JS is enabled or not.
	$classes[] = 'no-js';

	return $classes;
}

add_filter( 'body_class', 'starter_theme_body_classes' );

/**
 * Adds custom class to the array of posts classes.
 *
 * @param array $classes An array of CSS classes.
 *
 * @return array
 * @since 1.0.0
 *
 */
function starter_theme_post_classes( array $classes ): array {
	$classes[] = 'entry';

	return $classes;
}

add_filter( 'post_class', 'starter_theme_post_classes', 10, 3 );

/**
 * Remove the `no-js` class from body if JS is supported.
 *
 * @return void
 * @since 1.0.0
 *
 */
function starter_theme_supports_js(): void {
	echo '<script>document.body.classList.remove("no-js");</script>';
}

add_action( 'wp_footer', 'starter_theme_supports_js' );

/**
 * Add SVG support to media uploader
 *
 * @param $mimes
 *
 * @return array
 */
function starter_theme_mime_types( $mimes ): array {
	$mimes['svg'] = 'image/svg+xml';

	return $mimes;
}

add_filter( 'upload_mimes', 'starter_theme_mime_types' );

/**
 * Add required validation to ACF fields in Gutenberg
 *
 * @return void
 */
function starter_theme_validate_acf_fields(): void {
	foreach ( $_POST as $key => $value ) {
		if ( ! empty( $value ) && str_starts_with( $key, 'acf' ) ) {
			acf_validate_values( $value, $key );
		}
	}
}

add_action( 'acf/validate_save_post', 'starter_theme_validate_acf_fields', 5 );

/**
 * Clean up WordPress default tags, styles and scripts
 *
 * @return void
 */
function starter_theme_cleanup(): void {
	/**
	 * Cleanup <head> from unneeded stuff
	 */
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
	remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
	remove_action( 'wp_head', 'adjacent_post_rel_link_wp_head', 10, 0 );

	/**
	 * Remove emoji support
	 */
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	/**
	 * Removes oembed discovery links
	 */
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );

	/**
	 * Remove unwanted SVG filter injection WP
	 */
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}

add_action( 'init', 'starter_theme_cleanup' );

/**
 * Restrict blocks for specific Post types
 *
 * @param bool|array $allowed_blocks
 * @param WP_Block_Editor_Context $editor_context
 *
 * @return bool|array
 */
function dovira_allowed_block_types( bool|array $allowed_blocks, WP_Block_Editor_Context $editor_context ): bool|array {
	if ( $editor_context->post->post_type !== 'post' ) {
		return array(
			'acf/about',
			'acf/accordion',
			'acf/contacts',
			'acf/contacts-simple',
			'acf/employees',
			'acf/entities-grid',
			'acf/entity-links',
			'acf/files',
			'acf/gallery',
			'acf/hero',
			'acf/links-group',
			'acf/news',
			'acf/questionary',
			'acf/rich-text',
			'acf/services',
			'acf/text-form',
			'acf/text-image',
			'acf/vacancies',
		);
	}

	return true;
}

add_filter( 'allowed_block_types_all', 'dovira_allowed_block_types', 10, 2 );

/**
 * Disable autop for Contact Form 7 Forms
 */
add_filter( 'wpcf7_autop_or_not', '__return_false' );

/**
 * @return void
 */
function dovira_on_theme_deactivate(): void {
	remove_role( 'student' );
}

add_action( 'switch_theme', 'dovira_on_theme_deactivate' );

/**
 * @return void
 */
function dovira_on_theme_activate(): void {
	add_role( 'student', __( 'Student', 'dovira;' ), array(
			'take_course' => true,
		)
	);
}

add_action( 'after_switch_theme', 'dovira_on_theme_activate' );

/**
 * @return string
 */
function dovira_default_group_title_filter(): string {
	global $post_type;
	if ( 'group' === $post_type ) {

		return date( 'd-m-Y' );
	}

	return '';
}

add_filter( 'default_title', 'dovira_default_group_title_filter' );

if ( ! current_user_can( 'manage_options' ) ) {
	add_filter( 'show_admin_bar', '__return_false' );
}

/**
 * @param WP_User $user
 *
 * @return array
 */
function dovira_get_user_study_state( WP_User $user ): array {
	$user_study_state = get_user_meta( $user->ID, 'user_study_state', true ) ?: array();

	return is_array( $user_study_state ) ? $user_study_state : array();
}

/**
 * @param WP_User $user
 *
 * @return void
 */
function dovira_update_user_study_state( WP_User $user ): void {
	$user_study_state = get_user_meta( $user->ID, 'user_study_state', true ) ?: array();

	if ( ! is_array( $user_study_state ) ) {
		$user_study_state = array();
	}

	$chapters = get_terms( array(
		'taxonomy'   => 'chapter',
		'hide_empty' => false,
	) );

	if ( ! empty( $chapters ) ) {
		foreach ( $chapters as $chapter ) {
			if ( $chapter->parent !== 0 ) {
				$questions = get_posts( array(
					'numberposts' => - 1,
					'post_type'   => 'question',
					'tax_query'   => array(
						array(
							'taxonomy' => 'chapter',
							'field'    => 'term_id',
							'terms'    => $chapter->term_id
						)
					)
				) );
				if ( ! isset( $user_study_state[ $chapter->term_id ] ) ) {
					$user_study_state[ $chapter->term_id ] = array(
						'id'              => $chapter->term_id,
						'count_questions' => is_countable( $questions ) ? count( $questions ) : 0,
						'best_result'     => 0,
						'count_passed'    => 0
					);
				} elseif ( $user_study_state[ $chapter->term_id ]['count_questions'] === 0 ) {
					$user_study_state[ $chapter->term_id ]['count_questions'] = is_countable( $questions ) ? count( $questions ) : 0;
				}
			}
		}
	}

	update_user_meta( $user->ID, 'user_study_state', $user_study_state );
}

/**
 * @param array $cats
 * @param array $into
 * @param int $parent_id
 *
 * @return void
 */
function dovira_sort_terms_hierarchically( array &$cats, array &$into, int $parent_id = 0 ): void {
	foreach ( $cats as $i => $cat ) {
		if ( $cat->parent == $parent_id ) {
			$into[ $cat->term_id ] = $cat;
			unset( $cats[ $i ] );
		}
	}

	foreach ( $into as $topCat ) {
		$topCat->children = array();
		dovira_sort_terms_hierarchically( $cats, $topCat->children, $topCat->term_id );
	}
}

function dovira_get_hierarchical_chapters(): array {
	$chapters = get_terms( array(
		'taxonomy'   => 'chapter',
		'hide_empty' => false,
		'orderby'    => 'id',
	) );

	if ( ! empty( $chapters ) ) {
		$sorted_chapters = array();
		dovira_sort_terms_hierarchically( $chapters, $sorted_chapters );

		return $sorted_chapters;
	}

	return array();
}

/**
 * @param string $color
 *
 * @return bool
 */
function dovira_hex_is_light( string $color ): bool {
	$hex = str_replace( '#', '', $color );

	$c_r = hexdec( substr( $hex, 0, 2 ) );
	$c_g = hexdec( substr( $hex, 2, 2 ) );
	$c_b = hexdec( substr( $hex, 4, 2 ) );

	$brightness = ( ( $c_r * 299 ) + ( $c_g * 587 ) + ( $c_b * 114 ) ) / 1000;

	return $brightness > 155;
}

add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );


/**
 * Function for `save_post` action-hook.
 *
 * @param int $post_id Post ID.
 * @param WP_Post $post Post object.
 * @param bool $update Whether this is an existing post being updated.
 *
 * @return void
 */
function dovira_save_post_action( $post_id, $post, $update ) {
	if ( $post->post_status === 'publish' && ! wp_is_post_revision( $post_id ) && ! empty( $_POST['acf'] ) ) {
		if ( ! empty( $_POST['acf']['field_service_prices'] ) && is_array( $_POST['acf']['field_service_prices'] ) ) {
			$key_words_services = '';
			foreach ( $_POST['acf']['field_service_prices'] as $row ) {
				$key_words_services .= $row['field_service_prices_title'] . ' ';
			}

			if ( ! empty( $key_words_services ) ) {
				update_post_meta( $post_id, 'key_words_services', $key_words_services );
			}
		}
	}
}

add_action( 'save_post_service', 'dovira_save_post_action', 10, 3 );


/**
 * Outputs the microdata markup in JSON-LD format for the veterinary clinic,
 * including details about its main departments, their addresses, phone numbers,
 * opening hours, and descriptions.
 *
 * @return void
 */
function dovira_add_micro_markup(): void {

	if ( is_front_page() ) {
		$contacts     = dovira_get_acf_field( 'contacts_cities', 'option' );
		$micro_markup = [
			"@context" => "https://schema.org",
			"@type"    => "VeterinaryCare",
			"name"     => "Ветеринарна клініка",
			"url"      => get_home_url(),
		];
		if ( ! empty( $contacts ) ) {
			foreach ( $contacts as $contact ) {
				$micro_markup['department'][] = [
					"@type"        => "VeterinaryCare",
					"name"         => 'Філія ' . $contact['city'],
					"address"      => [
						"@type"           => "PostalAddress",
						"addressLocality" => $contact['city'],
						"streetAddress"   => $contact['address']
					],
					"telephone"    => array_map( function ( $phone ) {
						return $phone['number'];
					}, $contact['phones'] ),
					"openingHours" => $contact['schedule'],
					"description"  => str_replace( [ "\r", "\n" ], [ '', ' ' ], strip_tags( $contact['note'] ) ),
				];
			}
		}

		echo '<script type="application/ld+json">' . json_encode( $micro_markup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	}

	if ( is_singular( 'service' ) ) {
		global $post;
		$micro_markup = [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [
				[
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Головна',
					'item'     => 'https://dovira.vet/'
				],
				[
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => 'Послуги',
					'item'     => 'https://dovira.vet/services/'
				],
			]
		];

		if ( ! empty( $post ) ) {
			$micro_markup['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $post->post_title,
			];

			echo '<script type="application/ld+json">' . json_encode( $micro_markup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		}

	}
}

add_action( 'wp_head', 'dovira_add_micro_markup' );

/**
 * Redirects the user to the lowercase version of the current URL if it is not already lowercase.
 *
 * @return void
 */
function dovira_redirect_to_lowercase_url(): void {
	if ( $_SERVER['REQUEST_URI'] !== strtolower( $_SERVER['REQUEST_URI'] ) ) {
		wp_redirect( strtolower( $_SERVER['REQUEST_URI'] ), 301 );
	}
}

add_action( 'template_redirect', 'dovira_redirect_to_lowercase_url' );


/**
 * Updates the list of responsible persons for a post and returns the modified content.
 *
 * @param string $content The content of the post.
 * @param mixed $default_editor The default editor setting.
 *
 * @return string The content of the post.
 */
function dovira_set_responsible_persons( string $content, string $default_editor ): string {
	global $post;

	if ( ! empty( $post ) && ( $post->post_type === 'conversation' || $post->post_type === 'application' ) && ! empty( $post->post_title ) ) {
		$current_user = wp_get_current_user();

		if ( $current_user->exists() ) {
			$responsible_persons = get_post_meta( $post->ID, 'responsible_persons', true );
			if ( empty( $responsible_persons ) ) {
				$responsible_persons = [];
			}

			if ( ! isset( $responsible_persons[ $current_user->ID ] ) ) {
				$responsible_persons[ $current_user->ID ] = [
					'name'  => $current_user->first_name . ' ' . $current_user->last_name,
					'email' => $current_user->user_email,
				];
			}

			update_post_meta( $post->ID, 'responsible_persons', $responsible_persons );
		}
	}

	return $content;
}

add_filter( 'the_editor_content', 'dovira_set_responsible_persons', 10, 2 );

function dovira_custom_shortcode_atts_wpcf7_filter( $out, $pairs, $attributes ) {
	$my_attribute = 'vacancy';

	if ( isset( $attributes[ $my_attribute ] ) ) {
		$out[ $my_attribute ] = $attributes[ $my_attribute ];
	}

	return $out;
}

add_filter( 'shortcode_atts_wpcf7', 'dovira_custom_shortcode_atts_wpcf7_filter', 10, 3 );

/**
 * @param array $vars
 *
 * @return array
 */
function dovira_add_query_vars( array $vars ): array {
	$vars[] = 'status';
	$vars[] = 'vacancy';

	return $vars;
}

add_filter( 'query_vars', 'dovira_add_query_vars' );
