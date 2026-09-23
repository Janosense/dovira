<?php
/**
 * Tests for dovira\SearchStats\Renderer::blocks().
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use dovira\SearchStats\Renderer;
use dovira\SearchStats\SearchStats;
use dovira\SearchStats\TopQuery;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/TopQuery.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/SearchStats.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Renderer.php';

/**
 * The blocks against the template of docs/features/search-stats/FEATURE.md → UI.
 */
final class RendererTest extends TestCase {

	/**
	 * The posts the stubs know, by id: 1630 is the Russian page whose
	 * Ukrainian source is 12, 4242 no longer exists.
	 */
	private const TITLES = [
		12 => 'Послуги',
		18 => 'Приймальне відділення',
		77 => 'Кіт &amp; пес "Мур"',
	];

	private const TRANSLATIONS = [ 1630 => 12 ];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'pll_default_language' )->justReturn( 'uk' );
		Functions\when( 'pll_get_post' )->alias(
			static fn ( $id ) => self::TRANSLATIONS[ $id ] ?? ( isset( self::TITLES[ $id ] ) ? $id : 0 )
		);
		Functions\when( 'get_post' )->alias(
			static fn ( $id ) => isset( self::TITLES[ $id ] ) ? (object) [ 'ID' => $id ] : null
		);
		Functions\when( 'get_post_field' )->alias(
			static fn ( $field, $id ) => self::TITLES[ $id ] ?? ''
		);
	}

	/**
	 * The rows are handed over out of count order on purpose: the renderer
	 * prints the order it is given and never sorts.
	 */
	public function test_three_blocks_as_the_template_writes_them(): void {
		$stats = new SearchStats(
			[ new TopQuery( 'вакцинація', 0, 3, false ), new TopQuery( 'груминг', 0, 5, true ) ],
			[ new TopQuery( 'вакцинація', 0, 9, false ), new TopQuery( 'груминг', 0, 5, true ), new TopQuery( 'рентген', 0, 2, false ) ],
			[ new TopQuery( 'кастрація', 1630, 1, false ) ],
			[],
			[ new TopQuery( 'узі', 18, 2, false ) ],
			[ new TopQuery( 'узі', 18, 4, false ) ]
		);

		$this->assertSame(
			[
				"🔎 <b>Пошук по сайту</b>\nВчора:\n1. вакцинація — 3\n2. груминг — 5 · нічого не знайдено\nЗа 28 днів:\n1. вакцинація — 9\n2. груминг — 5 · нічого не знайдено\n3. рентген — 2",
				"🗂 <b>Пошук у переліку послуг</b>\nВчора:\n1. кастрація — Послуги — 1\nЗа 28 днів:\n—",
				"💊 <b>Пошук у послугах</b>\nВчора:\n1. узі — Приймальне відділення — 2\nЗа 28 днів:\n1. узі — Приймальне відділення — 4",
			],
			Renderer::blocks( $stats )
		);
	}

	public function test_a_block_with_two_empty_lists_is_not_contributed(): void {
		$stats = new SearchStats(
			[],
			[ new TopQuery( 'вакцинація', 0, 9, false ) ],
			[],
			[],
			[ new TopQuery( 'узі', 18, 2, false ) ],
			[]
		);

		$this->assertSame(
			[
				"🔎 <b>Пошук по сайту</b>\nВчора:\n—\nЗа 28 днів:\n1. вакцинація — 9",
				"💊 <b>Пошук у послугах</b>\nВчора:\n1. узі — Приймальне відділення — 2\nЗа 28 днів:\n—",
			],
			Renderer::blocks( $stats )
		);
	}

	public function test_no_searches_give_no_blocks(): void {
		$this->assertSame( [], Renderer::blocks( new SearchStats( [], [], [], [], [], [] ) ) );
	}

	/**
	 * Escaped with double encoding into the entities Telegram accepts. The
	 * second query is the case esc_html() gets wrong: WordPress keeps an
	 * entity it recognises, and Telegram refuses a named entity other than
	 * &lt; &gt; &amp; &quot;. A stored title is decoded first, so its "&amp;"
	 * is not encoded twice.
	 */
	public function test_queries_and_titles_are_escaped_for_telegram(): void {
		$this->assertSame( '1. &lt;b&gt;&quot;a&quot; &amp; &#039;b&#039; — 1', $this->site_row( '<b>"a" & \'b\'' ) );
		$this->assertSame( '1. &amp;nbsp; &amp;copy; — 1', $this->site_row( '&nbsp; &copy;' ) );

		$block = Renderer::blocks( new SearchStats( [], [], [ new TopQuery( 'кіт', 77, 1, false ) ], [], [], [] ) );

		$this->assertSame( '1. кіт — Кіт &amp; пес &quot;Мур&quot; — 1', explode( "\n", $block[0] )[2] );
	}

	public function test_a_long_query_is_cut_at_40_characters(): void {
		$this->assertSame( '1. ' . str_repeat( 'ж', 40 ) . ' — 1', $this->site_row( str_repeat( 'ж', 40 ) ), '40 characters print whole' );
		$this->assertSame( '1. ' . str_repeat( 'ж', 40 ) . '… — 1', $this->site_row( str_repeat( 'ж', 41 ) ), '41 are cut to 40' );
		$this->assertSame(
			'1. ' . str_repeat( 'а', 39 ) . '… — 1',
			$this->site_row( str_repeat( 'а', 39 ) . ' ' . str_repeat( 'б', 5 ) ),
			'no space is left in front of the ellipsis'
		);
		$this->assertSame(
			'1. ' . str_repeat( 'ж', 39 ) . '&amp;… — 1',
			$this->site_row( str_repeat( 'ж', 39 ) . '&&' ),
			'cut before escaping: an entity is never cut in half'
		);
	}

	public function test_the_context_is_named_by_its_ukrainian_post(): void {
		$block = Renderer::blocks( new SearchStats( [], [], [ new TopQuery( 'кастрація', 1630, 1, false ) ], [], [], [] ) );

		$this->assertSame( '1. кастрація — Послуги — 1', explode( "\n", $block[0] )[2] );
	}

	public function test_a_context_that_no_longer_exists_is_named_deleted(): void {
		$block = Renderer::blocks( new SearchStats( [], [], [], [], [ new TopQuery( 'узі', 4242, 3, false ) ], [] ) );

		$this->assertSame( '1. узі — (видалено) — 3', explode( "\n", $block[0] )[2] );
	}

	/**
	 * The first row of a site block made of one query searched once yesterday.
	 */
	private function site_row( string $query ): string {
		$blocks = Renderer::blocks( new SearchStats( [ new TopQuery( $query, 0, 1, false ) ], [], [], [], [], [] ) );

		return explode( "\n", $blocks[0] )[2];
	}
}
