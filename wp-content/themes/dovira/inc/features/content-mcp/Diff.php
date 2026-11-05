<?php

namespace dovira\ContentMcp;

/**
 * The one preview format of every write (FEATURE.md → Interfaces):
 * `{diff:[{target:{id, lang}, field, from, to}], warnings:[…]}`.
 */
final class Diff {

	/** @var list<array{target: array{id: int|null, lang: string|null}, field: string, from: mixed, to: mixed}> */
	private array $lines = [];

	/** @var list<string> */
	private array $warnings = [];

	/**
	 * Adds a line; a value that does not change adds nothing. A post that
	 * does not exist yet has the id null.
	 *
	 * @param mixed $from
	 * @param mixed $to
	 */
	public function add( ?int $id, ?string $lang, string $field, $from, $to ): self {
		if ( self::same( $from, $to ) ) {
			return $this;
		}

		$this->lines[] = [
			'target' => [
				'id'   => $id,
				'lang' => $lang,
			],
			'field'  => $field,
			'from'   => $from,
			'to'     => $to,
		];

		return $this;
	}

	public function warn( string $warning ): self {
		$this->warnings[] = $warning;

		return $this;
	}

	public function is_empty(): bool {
		return [] === $this->lines;
	}

	/**
	 * @return list<array{target: array{id: int|null, lang: string|null}, field: string, from: mixed, to: mixed}>
	 */
	public function lines(): array {
		return $this->lines;
	}

	/**
	 * @return list<string>
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * @return array{diff: list<array<string, mixed>>, warnings: list<string>}
	 */
	public function to_array(): array {
		return [
			'diff'     => $this->lines,
			'warnings' => $this->warnings,
		];
	}

	/**
	 * Numbers compare by value (`'450'` is 450); anything else strictly.
	 *
	 * @param mixed $from
	 * @param mixed $to
	 */
	private static function same( $from, $to ): bool {
		if ( is_numeric( $from ) && is_numeric( $to ) ) {
			return (float) $from === (float) $to;
		}

		return $from === $to;
	}
}
