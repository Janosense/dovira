<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/upload-media`: an image into the media library from a URL
 * or base64, behind the write protocol (`WriteAbility`, `MediaWriter`). The
 * preview downloads nothing; the result carries the new item as `media`.
 */
final class UploadMedia extends WriteAbility {

	public const NAME = 'dovira/upload-media';

	private MediaWriter $writer;

	/** @var (callable(): (array<string, mixed>|\WP_Error))|null */
	private $apply = null;

	private ?Diff $diff = null;

	public function __construct( ?MediaWriter $writer = null, ?callable $dump = null ) {
		parent::__construct( $dump );
		$this->writer = $writer ?? new MediaWriter();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$output                        = self::output_schema();
		$output['properties']['media'] = [
			'type'       => 'object',
			'properties' => [
				'id'     => [ 'type' => 'integer' ],
				'url'    => [ 'type' => 'string' ],
				'alt'    => [ 'type' => 'string' ],
				'mime'   => [ 'type' => 'string' ],
				'width'  => [ 'type' => 'integer' ],
				'height' => [ 'type' => 'integer' ],
			],
		];

		return [
			'label'               => 'Завантаження зображення',
			'description'         => 'Додає зображення до медіатеки: url (публічна http/https-адреса) або base64 (якщо клієнт має байти файлу), за потреби filename, alt (опис для незрячих, мовою сторінки), title, attach_to (id запису, до якого прикріпити). Лише растрові зображення (jpeg, png, gif, webp…; не svg), до 10 MB. Перегляд нічого не завантажує; після згоди — те саме з confirmation, і результат media {id, url, alt, mime, width, height}. Щоб поставити зображення в запис, далі — update-post / create-post з цим id (окремий перегляд).',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'url'       => [ 'type' => 'string' ],
					'base64'    => [ 'type' => 'string' ],
					'filename'  => [ 'type' => 'string' ],
					'alt'       => [ 'type' => 'string' ],
					'title'     => [ 'type' => 'string' ],
					'attach_to' => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				] + self::confirmation_schema(),
				'additionalProperties' => false,
			],
			'output_schema'       => $output,
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * `upload_files`, and `edit_post` of `attach_to` when that post exists (a
	 * missing one passes, so the agent hears "not found").
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$attach_to = (int) ( ( (array) $input )['attach_to'] ?? 0 );

		return $attach_to <= 0 || ! get_post( $attach_to ) || current_user_can( 'edit_post', $attach_to );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->writer->plan( $payload );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$this->apply = $plan['apply'];
		$this->diff  = $plan['diff'];

		return $plan['diff'];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>, media: array<string, mixed>}|\WP_Error
	 */
	protected function write( array $payload ) {
		if ( null === $this->apply ) {
			$diff = $this->preview( $payload );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
		}

		$media = ( $this->apply )();
		if ( is_wp_error( $media ) ) {
			return $media;
		}

		return [
			'written'  => [ (int) $media['id'] ],
			'warnings' => null !== $this->diff ? $this->diff->warnings() : [],
			'media'    => $media,
		];
	}
}
