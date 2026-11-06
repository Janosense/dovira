<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/update-price-row`: one row of a service's price list —
 * its title, description or price in this city — behind the write protocol
 * (`WriteAbility`). The price follows into the Russian copy (`PriceRows`).
 */
final class UpdatePriceRow extends WriteAbility {

	public const NAME = 'dovira/update-price-row';

	private PriceRows $rows;

	/** @var array{diff: Diff, writes: array<int, array{key: string, rows: list<array<string, mixed>>}>}|null */
	private ?array $plan = null;

	public function __construct( ?PriceRows $rows = null, ?callable $dump = null ) {
		parent::__construct( $dump );
		$this->rows = $rows ?? new PriceRows();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Зміна рядка цін',
			'description'         => 'Змінює один рядок прайсу послуги (service_id і row_index з find-price-rows): price — ціна в місті цього сайту, title, description. Спершу викличте без confirmation: повернеться diff і токен; покажіть diff людині й після її згоди викличте з тими самими даними та confirmation. Ціна змінюється в українській копії й сама переноситься в російську (рядок з тим самим індексом); текст російського рядка — окремий виклик з id російської копії.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'service_id'  => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'row_index'   => [
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Індекс рядка з find-price-rows, від 0.',
					],
					'title'       => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'price'       => [
						'type'        => 'number',
						'description' => 'Нова ціна в гривнях, більше за 0.',
					],
				] + self::confirmation_schema(),
				'required'             => [ 'service_id', 'row_index' ],
				'additionalProperties' => false,
			],
			'output_schema'       => self::output_schema(),
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * A missing post passes, so the agent hears "not found"; an existing one
	 * needs `edit_post`.
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$id = (int) ( ( (array) $input )['service_id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return true;
		}

		return current_user_can( 'edit_post', $id );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->rows->update(
			(int) ( $payload['service_id'] ?? 0 ),
			(int) ( $payload['row_index'] ?? -1 ),
			$payload
		);
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$this->plan = $plan;

		return $plan['diff'];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>}|\WP_Error
	 */
	protected function write( array $payload ) {
		$plan = $this->plan;
		if ( null === $plan ) {
			$diff = $this->preview( $payload );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
			$plan = (array) $this->plan;
		}

		return [
			'written'  => $this->rows->apply( $plan['writes'] ),
			'warnings' => $plan['diff']->warnings(),
		];
	}
}
