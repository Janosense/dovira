<?php
/**
 * Tests for dovira_conversation_telegram_message(): the city of the sending site and the link to its own admin.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\Telegram;

use Brain\Monkey\Functions;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/utils/telegram.php';

/**
 * The site URL decides the city; admin_url() of the same site gives the link.
 */
final class ConversationMessageTest extends TestCase {

	private function on_site( string $site_url ): void {
		Functions\when( 'get_site_url' )->justReturn( $site_url );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => $site_url . '/wp-admin/' . $path );
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: string}>
	 */
	public static function messages(): array {
		return [
			'Kharkiv, contact form'   => [
				'https://dovira.vet',
				6,
				'Надійшло нове звернення з контактної форми із сайту в Харкові. <a href="https://dovira.vet/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>',
			],
			'Kharkiv, franchise form' => [
				'https://dovira.vet',
				1430,
				'Надійшло нове звернення з форми заявки на Франшизу із сайту в Харкові. <a href="https://dovira.vet/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>',
			],
			'Kyiv, contact form'      => [
				'https://kyiv.dovira.vet',
				6,
				'Надійшло нове звернення з контактної форми із сайту в Києві. <a href="https://kyiv.dovira.vet/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>',
			],
			'Kyiv, franchise form'    => [
				'https://kyiv.dovira.vet',
				1430,
				'Надійшло нове звернення з форми заявки на Франшизу із сайту в Києві. <a href="https://kyiv.dovira.vet/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>',
			],
			'a site without kyiv in its URL counts as Kharkiv' => [
				'https://dovira.ddev.site',
				6,
				'Надійшло нове звернення з контактної форми із сайту в Харкові. <a href="https://dovira.ddev.site/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>',
			],
		];
	}

	#[DataProvider( 'messages' )]
	public function test_the_message_names_the_city_and_links_to_its_own_admin( string $site_url, int $form_id, string $expected ): void {
		$this->on_site( $site_url );

		self::assertSame( $expected, dovira_conversation_telegram_message( $form_id ) );
	}

	public function test_the_link_is_escaped_for_telegram_html(): void {
		Functions\when( 'get_site_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'admin_url' )->justReturn( 'https://dovira.vet/wp-admin/edit.php?post_type=conversation&lang="uk"' );

		self::assertStringContainsString(
			'<a href="https://dovira.vet/wp-admin/edit.php?post_type=conversation&amp;lang=&quot;uk&quot;">',
			dovira_conversation_telegram_message( 6 )
		);
	}
}
