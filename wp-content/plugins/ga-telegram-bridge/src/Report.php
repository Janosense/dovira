<?php
/**
 * One day's report, as data.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Everything the message needs and nothing else, already computed.
 *
 * The four optional blocks distinguish two states the renderer must not
 * confuse: null means the block is switched off in the settings and is left out
 * of the message entirely, while an empty array means it is switched on and GA
 * had nothing to report for it.
 *
 * Shares and changes are worked out when the report is built, not when it is
 * rendered, because the `gatb_report_data` filter hands this object to other
 * people's code as the finished numbers.
 */
final class Report {

	/**
	 * Holds one finished report.
	 *
	 * @param string                                                       $date                       The day the report is about, Y-m-d in the property's reporting time zone.
	 * @param string                                                       $time_zone                  The property's reporting time zone, as GA reported it.
	 * @param int                                                          $visitors_yesterday         Active users yesterday.
	 * @param float                                                        $visitors_average_7_days    Daily average of the seven days before yesterday.
	 * @param int                                                          $visitors_28_days           Active users over the last 28 days.
	 * @param int                                                          $visitors_previous_28_days  Active users over the 28 days before those.
	 * @param int|null                                                     $visitors_change_vs_average Yesterday against the seven-day average, whole per cent.
	 * @param int|null                                                     $visitors_change_28_days    The last 28 days against the previous 28, whole per cent.
	 * @param list<array{title: string, path: string, views: int}>|null    $pages_yesterday            Top pages of yesterday, or null when the block is off.
	 * @param list<array{title: string, path: string, views: int}>|null    $pages_28_days              Top pages of the last 28 days, or null when the block is off.
	 * @param list<array{label: string, value: int, share: int|null}>|null $channels                Traffic sources, or null when the block is off.
	 * @param list<array{label: string, value: int, share: int|null}>|null $cities                  Cities, or null when the block is off.
	 * @param list<array{label: string, value: int, share: int|null}>|null $devices                 Devices, or null when the block is off.
	 */
	public function __construct(
		public readonly string $date,
		public readonly string $time_zone,
		public readonly int $visitors_yesterday,
		public readonly float $visitors_average_7_days,
		public readonly int $visitors_28_days,
		public readonly int $visitors_previous_28_days,
		public readonly ?int $visitors_change_vs_average,
		public readonly ?int $visitors_change_28_days,
		public readonly ?array $pages_yesterday,
		public readonly ?array $pages_28_days,
		public readonly ?array $channels,
		public readonly ?array $cities,
		public readonly ?array $devices
	) {
	}

	/**
	 * Whether a block is switched on, whatever GA had to say about it.
	 *
	 * @param string $block One of the optional block names.
	 */
	public function has_block( string $block ): bool {
		$blocks = array(
			'pages'    => $this->pages_yesterday,
			'channels' => $this->channels,
			'cities'   => $this->cities,
			'devices'  => $this->devices,
		);

		return array_key_exists( $block, $blocks ) && null !== $blocks[ $block ];
	}
}
