<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/create-translation`: the Russian copy of a Ukrainian post
 * that has none, behind the write protocol (`WriteAbility`, `Translator`).
 * An existing copy is changed with `update-post`.
 */
final class CreateTranslation extends WriteAbility {

	public const NAME = 'dovira/create-translation';

	private Translator $translator;

	/** @var (callable(): (int|\WP_Error))|null */
	private $apply = null;

	private ?Diff $diff = null;

	public function __construct( ?Translator $translator = null, ?callable $dump = null ) {
		parent::__construct( $dump );
		$this->translator = $translator ?? new Translator();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$fields = UpdatePost::fields_schema();

		return [
			'label'               => 'Російська копія',
			'description'         => 'Створює російську копію українського запису, у якого її ще немає, і зв’язує їх у Polylang. id — український запис; title — російський заголовок; status — той, який назвала людина (без значення за замовчуванням). Переклад пишете ви: acf {поле: російське значення}, blocks — увесь вміст у форматі get-post (у блоці acf/* досить назвати перекладені поля), terms — російські терміни. Чого не передано, переноситься з української як є: значення ACF, вміст, рубрики (їхні російські переклади), батьківський запис (його російська копія), шаблон, головне зображення, дата. Ціни послуги — ті самі; acf.prices у перекладі — рядки {title, description} у тому ж порядку і тій самій кількості. Якщо копія вже є — update-post на ній. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id' => [
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Український запис, з якого робиться копія.',
					],
				] + $fields + self::confirmation_schema(),
				'required'             => [ 'id', 'title', 'status' ],
				'additionalProperties' => false,
			],
			'output_schema'       => self::output_schema(),
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * `edit_post` of the source and the type's `create_posts`, plus its
	 * `publish_posts` for `publish` / `private`. A missing source passes, so
	 * the agent hears "not found".
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$input  = (array) $input;
		$id     = (int) ( $input['id'] ?? 0 );
		$source = $id > 0 ? get_post( $id ) : null;
		if ( ! $source ) {
			return true;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return false;
		}

		$object = get_post_type_object( (string) $source->post_type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
			return false;
		}

		return ! in_array( $input['status'] ?? '', [ 'publish', 'private' ], true ) || current_user_can( $object->cap->publish_posts );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->translator->plan( $payload );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$this->apply = $plan['apply'];
		$this->diff  = $plan['diff'];

		return $plan['diff'];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>}|\WP_Error
	 */
	protected function write( array $payload ) {
		if ( null === $this->apply ) {
			$diff = $this->preview( $payload );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
		}

		$id = ( $this->apply )();
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return [
			'written'  => [ (int) $id ],
			'warnings' => null !== $this->diff ? $this->diff->warnings() : [],
		];
	}
}
