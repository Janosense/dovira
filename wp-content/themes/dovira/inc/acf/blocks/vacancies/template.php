<?php
/**
 * Vacancies Block Template.
 *
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during backend preview render.
 * @var int $post_id The post ID the block is rendering content against.
 *          This is either the post ID currently being displayed inside a query loop,
 *          or the post ID of the post hosting this block.
 * @var array $context The context provided to the block by the post or it's parent block.
 */
$block_id            = ( isset( $block['anchor'] ) && ! empty( $block['anchor'] ) ) ? $block['anchor'] : $block['id'];
$is_visible          = dovira_get_acf_field( 'is_visible' );
$heading             = dovira_get_acf_field( 'heading' );
$heading_color       = dovira_get_acf_field( 'heading_color' );
$heading_level       = dovira_get_acf_field( 'heading_level' );
$heading_style       = dovira_get_acf_field( 'heading_style' );
$caption             = dovira_get_acf_field( 'caption' );
$caption_color       = dovira_get_acf_field( 'caption_color' );
$header_text_align   = dovira_get_acf_field( 'header_text_align' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$background_image    = dovira_get_acf_field( 'background_image' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$cta_style           = dovira_get_acf_field( 'cta_style' );

$vacancies = get_posts( array(
	'post_type'   => 'vacancy',
	'numberposts' => - 1,
	'meta_query'  => array(
		array(
			'key'   => 'is_open',
			'value' => 1,
		)
	)
) );

$cities = get_terms( array(
	'taxonomy'   => 'service-city',
	'hide_empty' => false,
) );

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- GALLERY start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> vacancies <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>;"<?php endif; ?>>
		<?php if ( ! empty( $background_image ) ) :
			echo wp_get_attachment_image( $background_image['id'], 'full', false, array(
				'class' => 'section__background-image',
				'alt'   => $heading,
			) );
		endif; ?>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div class="wrapper vacancies__wrapper">
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="rich-text__caption caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $cities ) ) : ?>
				<div class="city-toggle">
					<?php
					$toggles_index = 0;
					foreach ( $cities as $city ) :
						if ( $toggles_index === 0 ) :
							$active_city = $city->slug;
						endif; ?>
						<button
							data-city="<?= $city->slug; ?>"
							class="city-toggle__button <?php if ( $toggles_index === 0 ): ?>city-toggle__button--active<?php endif; ?>"
							type="button"><?= dovira_translate_string( $city->name ); ?>
						</button>
						<?php $toggles_index ++;
					endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $vacancies ) ) : ?>
				<ul class="vacancies__list" data-city-toggle-list>
					<?php foreach ( $vacancies as $vacancy ) :
						$cities_str = '';
						$cities = wp_get_object_terms( $vacancy->ID, 'service-city' );
						if ( ! empty( $cities ) && ! is_wp_error( $cities ) ) :
							$cities_str = implode( ', ', array_map( fn( $item ) => $item->slug, $cities ) );
						endif; ?>
						<li class="vacancies__item" data-city="<?= $cities_str; ?>"
							<?php if ( strpos( $cities_str, $active_city ) === false ) : ?>style="display: none;"<?php endif; ?>>
							<div class="vacancies__layer">
								<div class="vacancies__icon-holder">
									<img src="<?= TEMPLATE_DIR_URI; ?>/assets/images/icon-vacancy.svg" alt="">
								</div>
								<?php if ( ! is_wp_error( $cities ) && ! empty( $cities ) ) : ?>
									<div class="vacancies__locations">
										<?php foreach ( $cities as $city ): ?>
											<div class="vacancies__location">
												<svg width="20" height="20" viewBox="0 0 20 20" fill="none"
													 xmlns="http://www.w3.org/2000/svg">
													<path opacity="0.4"
														  d="M17.1829 7.04183C16.3079 3.19183 12.9496 1.4585 9.99959 1.4585C9.99959 1.4585 9.99959 1.4585 9.99126 1.4585C7.04959 1.4585 3.68292 3.1835 2.80792 7.0335C1.83292 11.3335 4.46626 14.9752 6.84959 17.2668C7.73292 18.1168 8.86626 18.5418 9.99959 18.5418C11.1329 18.5418 12.2663 18.1168 13.1413 17.2668C15.5246 14.9752 18.1579 11.3418 17.1829 7.04183Z"
														  fill="#C06F94"/>
													<path
														d="M10 11.2168C11.4497 11.2168 12.625 10.0415 12.625 8.5918C12.625 7.14205 11.4497 5.9668 10 5.9668C8.55025 5.9668 7.375 7.14205 7.375 8.5918C7.375 10.0415 8.55025 11.2168 10 11.2168Z"
														fill="#C06F94"/>
												</svg>
												<?= dovira_translate_string( $city->name ); ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
							<h2 class="vacancies__title heading heading--h5"><?= $vacancy->post_title; ?></h2>
							<a href="<?= get_permalink( $vacancy->ID ); ?>"
							   class="button button--black-border vacancies__cta">Ознайомитись</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $cta ) ) : ?>
				<div class="section__cta-holder section__cta-holder--align-<?= $cta_align; ?>">
					<a href="<?= $cta['url']; ?>" target="<?= $cta['target']; ?>"
					   class="button button--<?= $cta_style; ?> section__cta">
						<?= $cta['title'];; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- GALLERY end -->
<?php endif; ?>
