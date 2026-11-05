<?php
/**
 * Tests for dovira\ContentMcp\Diff: the one preview format.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use dovira\ContentMcp\Diff;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Diff.php';

/**
 * `{diff:[{target:{id, lang}, field, from, to}], warnings}`.
 */
final class DiffTest extends TestCase {

	public function test_a_line_names_the_target_the_field_and_both_values(): void {
		$diff = ( new Diff() )
			->add( 18, 'uk', 'prices[3].price', 450, 550 )
			->add( 1641, 'ru', 'prices[3].price', null, 550 )
			->warn( 'Увага' );

		$this->assertSame(
			[
				'diff'     => [
					[
						'target' => [ 'id' => 18, 'lang' => 'uk' ],
						'field'  => 'prices[3].price',
						'from'   => 450,
						'to'     => 550,
					],
					[
						'target' => [ 'id' => 1641, 'lang' => 'ru' ],
						'field'  => 'prices[3].price',
						'from'   => null,
						'to'     => 550,
					],
				],
				'warnings' => [ 'Увага' ],
			],
			$diff->to_array()
		);
		$this->assertFalse( $diff->is_empty() );
	}

	public function test_an_unchanged_value_adds_no_line(): void {
		$diff = ( new Diff() )
			->add( 18, 'uk', 'prices[3].price', '450', 450 )
			->add( 18, 'uk', 'prices[3].title', 'Прийом', 'Прийом' );

		$this->assertTrue( $diff->is_empty() );
		$this->assertSame( [], $diff->lines() );
		$this->assertSame( [], $diff->warnings() );
	}

	public function test_null_and_zero_differ(): void {
		$this->assertFalse( ( new Diff() )->add( 1, null, 'f', null, '' )->is_empty() );
	}
}
