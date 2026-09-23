<?php
/**
 * Tests for dovira\SearchStats\Normalizer.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use dovira\SearchStats\Normalizer;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Normalizer.php';

/**
 * Case, the edges, inner whitespace and non-Latin text.
 */
final class NormalizerTest extends TestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function texts(): array {
		return [
			'uppercase Cyrillic'             => [ 'ВАКЦИНАЦІЯ', 'вакцинація' ],
			'Ukrainian letters'              => [ 'ЇЖАК Ґудзик ЄНОТ', 'їжак ґудзик єнот' ],
			'Latin'                          => [ 'Rabies Vaccine', 'rabies vaccine' ],
			'mixed script and digits'        => [ 'УЗД 3D Кота', 'узд 3d кота' ],
			'spaces at the edges'            => [ '  кіт  ', 'кіт' ],
			'tabs and newlines at the edges' => [ "\t\nкіт\r\n", 'кіт' ],
			'inner runs of whitespace'       => [ "стерилізація   \t\n кота", 'стерилізація кота' ],
			'a non-breaking space'           => [ "стерилізація\u{00A0}кота", 'стерилізація кота' ],
			'whitespace only'                => [ " \t\n ", '' ],
		];
	}

	#[DataProvider( 'texts' )]
	public function test_normalize( string $text, string $expected ): void {
		$this->assertSame( $expected, Normalizer::normalize( $text ) );
	}

	public function test_invalid_utf8_normalizes_to_an_empty_string(): void {
		$this->assertSame( '', Normalizer::normalize( "a\xffb" ) );
	}
}
