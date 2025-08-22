<?php
/**
 * Accordion Block Template.
 *
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during AJAX preview.
 * @var int|string $post_id The post ID this block is saved to.
 *
 * @package syndicode2
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
$accordion           = get_field( 'accordion_items' );
?>
<!-- ACCORDION start -->
<section
	class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> accordion <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
	<div class="wrapper accordion__wrapper">
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
		<?php if ( ! empty( $accordion ) ) : ?>
			<ul class="accordion__list">
				<?php foreach ( $accordion as $item ) : ?>
					<li class="accordion__item">
						<h4 class="accordion__title heading heading--simple">
							<?= $item['accordion-item-title']; ?>
							<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" class="accordion__arrow">
								<path d="M12 5V19M12 19L6 13M12 19L18 13" stroke="#000000" stroke-width="2"
									  stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</h4>
						<div class="accordion__text-holder">
							<p class="text accordion__text">
								<?= $item['accordion-item-text']; ?>
							</p>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
<!-- ACCORDION end -->

