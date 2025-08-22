<?php
/**
 * About Block Template.
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
$years               = dovira_get_acf_field( 'years' );

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- GALLERY start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> about <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
		<div class="wrapper about__wrapper">
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="about__caption caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $years ) ) : ?>
				<div class="about__swiper about__swiper--years swiper" id="swiper-about-years">
					<div class="swiper-wrapper">
						<?php foreach ( $years as $year ): ?>
							<div class="swiper-slide"><?= $year['year']; ?></div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="about__swiper-navs">
					<div class="about__swiper-nav about__swiper-nav--prev swiper-button-prev"></div>
					<div class="about__swiper-nav about__swiper-nav--next  swiper-button-next"></div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $years ) ) : ?>
			<div class="wrapper wrapper--tight">
				<div class="about__swiper about__swiper--content swiper" id="swiper-about-content">
					<div class="swiper-wrapper">
						<?php foreach ( $years as $year ): ?>
							<div class="swiper-slide">
								<?php if ( ! empty( $year['period'] ) ) : ?>
									<span class="about__period"><?= $year['period']; ?></span>
								<?php endif; ?>
								<h2 class="about__title heading heading--h3"><?= $year['title']; ?></h2>
								<div class="about__text"><?= $year['text']; ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- GALLERY end -->
<?php endif; ?>
