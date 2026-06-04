<?php
/**
 * Services Block Template.
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
$is_show_all         = dovira_get_acf_field( 'is_show_all' );
$is_search_visible   = dovira_get_acf_field( 'is_search_visible' );
$grid_columns        = dovira_get_acf_field( 'grid_columns' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$background_image    = dovira_get_acf_field( 'background_image' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$cta_style           = dovira_get_acf_field( 'cta_style' );

if ( $is_show_all ) :
	$services = get_posts( [
		'numberposts' => - 1,
		'post_type'   => 'service',
		'order'       => 'ASC',
		'post_parent' => 0,
	] );
else :
	$services = dovira_get_acf_field( 'services' );
endif;

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- SERVICES start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> services <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
		<div class="wrapper services__wrapper">
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
			<?php if ( $is_search_visible ) : ?>
				<div class="services__search">
					<label class="services__search-field">
						<input type="text" id="services-search-input"
						       placeholder="Швидкий пошук по послугах:">
						<button type="reset" id="services-search-reset"><?= pll__( 'Reset' ); ?>
							<svg width="27px" height="27px" viewBox="0 -0.5 25 25" fill="none"
							     xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" clip-rule="evenodd"
								      d="M7.30524 15.7137C6.4404 14.8306 5.85381 13.7131 5.61824 12.4997C5.38072 11.2829 5.50269 10.0233 5.96924 8.87469C6.43181 7.73253 7.22153 6.75251 8.23924 6.05769C10.3041 4.64744 13.0224 4.64744 15.0872 6.05769C16.105 6.75251 16.8947 7.73253 17.3572 8.87469C17.8238 10.0233 17.9458 11.2829 17.7082 12.4997C17.4727 13.7131 16.8861 14.8306 16.0212 15.7137C14.8759 16.889 13.3044 17.5519 11.6632 17.5519C10.0221 17.5519 8.45059 16.889 7.30524 15.7137V15.7137Z"
								      stroke="#3c3c3c" stroke-width="1.5" stroke-linecap="round"
								      stroke-linejoin="round"/>
								<path
									d="M11.6702 7.20292C11.2583 7.24656 10.9598 7.61586 11.0034 8.02777C11.0471 8.43968 11.4164 8.73821 11.8283 8.69457L11.6702 7.20292ZM13.5216 9.69213C13.6831 10.0736 14.1232 10.2519 14.5047 10.0904C14.8861 9.92892 15.0644 9.4888 14.9029 9.10736L13.5216 9.69213ZM16.6421 15.0869C16.349 14.7943 15.8741 14.7947 15.5815 15.0879C15.2888 15.381 15.2893 15.8559 15.5824 16.1485L16.6421 15.0869ZM18.9704 19.5305C19.2636 19.8232 19.7384 19.8228 20.0311 19.5296C20.3237 19.2364 20.3233 18.7616 20.0301 18.4689L18.9704 19.5305ZM11.8283 8.69457C12.5508 8.61801 13.2384 9.02306 13.5216 9.69213L14.9029 9.10736C14.3622 7.83005 13.0496 7.05676 11.6702 7.20292L11.8283 8.69457ZM15.5824 16.1485L18.9704 19.5305L20.0301 18.4689L16.6421 15.0869L15.5824 16.1485Z"
									fill="#3c3c3c"/>
							</svg>
						</button>
					</label>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $services ) ) : ?>
				<ul class="services__list services__list--<?= $grid_columns; ?>">
					<?php foreach ( $services as $service ):
						$cover = dovira_get_acf_field( 'image', $service->ID );
						$prices = dovira_get_acf_field( 'prices', $service->ID );
						if ( $is_search_visible ) :
							$key_words = dovira_get_acf_field( 'key_words', $service->ID );
							$key_words .= ',' . mb_strtolower( $service->post_title, 'UTF-8' );
							if ( ! empty( $prices ) ) :
								foreach ( $prices as $price ) :
									$key_words .= ',' . mb_strtolower( $price['title'], 'UTF-8' );
								endforeach;
							endif;
						endif; ?>
						<li class="services__item"
						    <?php if ( $is_search_visible ) : ?>data-search="<?= $key_words; ?>"<?php endif; ?>>
							<a href="<?= get_permalink( $service->ID ); ?>" class="services__link">
								<?= wp_get_attachment_image( $cover['id'], 'full', false, array(
									'class' => 'services__image',
									'alt'   => 'Ветеринарна клініка: ' . $service->post_title,
								) ); ?>
								<span
									class="services__counter"><?= pll__( 'Services:' ); ?> <?= is_countable( $prices ) ? count( $prices ) : 0 ?></span>
								<h3 class="heading heading--h6 services__title"><?= $service->post_title; ?></h3>
								<span class="button button--black-border services__cta"><?= pll__( 'More' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $cta ) ) : ?>
				<div class="section__cta-holder section__cta-holder--align-<?= $cta_align; ?>">
					<a href="<?= $cta['url']; ?>" target="<?= $cta['target']; ?>"
					   class="button button--<?= $cta_style; ?> section__cta">
						<?= $cta['title']; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- SERVICES end -->
<?php endif; ?>
