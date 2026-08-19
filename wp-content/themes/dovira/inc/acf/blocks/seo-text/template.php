<?php
/**
 * Entities Grid Block Template.
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
$text                = dovira_get_acf_field( 'text' );
$text_color          = dovira_get_acf_field( 'text_color' );
$text_align          = dovira_get_acf_field( 'text_align' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$cta_style           = dovira_get_acf_field( 'cta_style' );
$container_max_width          = dovira_get_acf_field( 'container_max_width' );

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- RICH TEXT start -->
	<section id="<?= $block_id; ?>"
		class="section section--mb-standard <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> seo-text <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>; --seo-text-fade-color: <?= $background_color; ?>;"<?php endif; ?>>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div class="wrapper seo-text__wrapper" <?php if (! empty( $container_max_width )) : ?>style="max-width: <?= $container_max_width; ?>px"<?php endif; ?>>
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="seo-text__caption caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $text ) ) : ?>
				<div class="seo-text__text">
					<div id="<?= $block_id; ?>-text"
						 class="seo-text__content seo-text__content--align-<?= $text_align; ?>"
						 style="color:<?= $text_color; ?>;">
						<?= $text; ?>
					</div>
					<?php // Revealed by JS only when the text is taller than the collapsed height. ?>
					<button type="button" class="seo-text__toggle" aria-controls="<?= $block_id; ?>-text"
							aria-expanded="false"
							data-label-collapsed="<?= esc_attr( pll__( 'Show more' ) ); ?>"
							data-label-expanded="<?= esc_attr( pll__( 'Show less' ) ); ?>" hidden>
						<?= pll__( 'Show more' ); ?>
					</button>
				</div>
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
	<!-- RICH TEXT end -->
<?php endif; ?>
