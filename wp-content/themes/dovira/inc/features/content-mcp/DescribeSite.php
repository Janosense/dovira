<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/describe-site`: which install the connector is talking to,
 * its languages, who is connected, and the content model — post types with
 * their ACF fields, the theme's blocks with theirs, taxonomies with their
 * terms (`Schema`).
 */
final class DescribeSite {

	public const NAME = 'dovira/describe-site';

	public const KHARKIV = 'kharkiv';
	public const KYIV = 'kyiv';

	private bool $polylang;
	private Schema $schema;

	/**
	 * @param bool|null   $polylang Whether Polylang's API is there; null asks.
	 *                              Tests pass false, since a function stubbed
	 *                              once stays declared for the whole run.
	 * @param Schema|null $schema   The content model; null builds the default.
	 */
	public function __construct( ?bool $polylang = null, ?Schema $schema = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_languages_list' );
		$this->schema   = $schema ?? new Schema();
	}

	/**
	 * The arguments of `wp_register_ability()`.
	 *
	 * The ability takes no input, so it has no input schema: core accepts only
	 * a null input then, and the adapter turns a client's `{}` into null and
	 * publishes `{type: object}`. `meta.mcp.public` is false so the adapter's
	 * default server never discovers or executes it — the `dovira` server
	 * lists it by name (TECH-STACK ANTI-PATTERNS).
	 *
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Опис сайту',
			'description'         => 'Яке це місто (Харків чи Київ), які мови має сайт, хто підключений (логін, ролі, чи може видаляти — лише адміністратор) і з чого складається контент: типи записів з їхніми полями ACF, блоки сторінок з їхніми полями, рубрики, мітки й міста. Викликай першим у кожній розмові.',
			'category'            => Catalogue::CATEGORY,
			'execute_callback'    => static fn() => ( new self() )->execute(),
			'permission_callback' => [ self::class, 'can_read' ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'city'      => [
						'type' => 'string',
						'enum' => [ self::KHARKIV, self::KYIV ],
					],
					'languages' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'user'      => [
						'type'       => 'object',
						'properties' => [
							'login'      => [ 'type' => 'string' ],
							'roles'      => [
								'type'  => 'array',
								'items' => [ 'type' => 'string' ],
							],
							'can_delete' => [ 'type' => 'boolean' ],
						],
					],
					'post_types' => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
					'blocks'     => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
					'taxonomies' => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
				],
			],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				'mcp'          => [ 'public' => false ],
			],
		];
	}

	/**
	 * Any connected user who can read the admin may describe the site.
	 */
	public static function can_read(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * @return array<string, mixed> `{city, languages, user, post_types, blocks, taxonomies}`.
	 */
	public function execute(): array {
		$user = wp_get_current_user();

		return array_merge( [
			'city'      => self::city( (string) get_site_url() ),
			'languages' => $this->polylang ? array_values( (array) pll_languages_list() ) : [],
			'user'      => [
				'login'      => (string) $user->user_login,
				'roles'      => array_values( (array) $user->roles ),
				'can_delete' => current_user_can( 'manage_options' ),
			],
		], $this->schema->describe() );
	}

	/**
	 * Kharkiv vs Kyiv, decided from the site URL only (root invariant 4).
	 */
	public static function city( string $site_url ): string {
		return str_contains( $site_url, 'kyiv' ) ? self::KYIV : self::KHARKIV;
	}
}
