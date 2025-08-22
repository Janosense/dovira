<?php
/**
 * Employees Block Template.
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
$show_all_employees  = dovira_get_acf_field( 'show_all_employees' );
$employees           = dovira_get_acf_field( 'employees' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$background_image    = dovira_get_acf_field( 'background_image' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$text_color          = dovira_get_acf_field( 'text_color' );
$text_align          = dovira_get_acf_field( 'text_align' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$cta_style           = dovira_get_acf_field( 'cta_style' );
$cities              = get_terms( array(
	'taxonomy'   => 'service-city',
	'hide_empty' => false,
) );

if ( $show_all_employees ) :
	$employees = get_posts( array(
		'numberposts' => - 1,
		'post_type'   => 'employee',
	) );
endif;

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- employees start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> employees <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
		<div class="wrapper employees__wrapper">
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $employees ) ) : ?>
				<?php if ( $show_all_employees && ! empty( $cities ) ) : ?>
					<div class="employees__city-toggles">
						<?php
						$toggles_index = 0;
						foreach ( $cities as $city ) :
							if ( $toggles_index === 0 ) :
								$active_city = $city->slug;
							endif; ?>
							<button
								data-city="<?= $city->slug; ?>"
								class="employees__city-toggle <?php if ( $toggles_index === 0 ): ?>employees__city-toggle--active<?php endif; ?>"
								type="button"><?= $city->name; ?>
							</button>
							<?php $toggles_index ++;
						endforeach; ?>
					</div>
				<?php endif; ?>
				<ul class="employees__grid employees__grid--5"
					style="color: <?= $text_color; ?>; text-align: <?= $text_align; ?>">
					<?php foreach ( $employees as $employee ) :
						$photo = dovira_get_acf_field( 'photo', $employee->ID );
						$position = dovira_get_acf_field( 'position', $employee->ID );
						$cities_str = '';
						$cities = wp_get_object_terms( $employee->ID, 'service-city' );
						if ( ! empty( $cities ) && ! is_wp_error( $cities ) ) :
							$cities_str = implode( ', ', array_map( fn( $item ) => $item->slug, $cities ) );
						endif;
						?>
						<li class="employees__item"
							<?php if ( $show_all_employees && strpos( $cities_str, $active_city ) === false ) : ?>style="display: none;"<?php endif; ?>
							data-city="<?= $cities_str; ?>">
							<a href="<?= get_permalink( $employee->ID ); ?>" class="employees__link">
								<div class="employees__photo-holder">
									<?= wp_get_attachment_image( $photo['id'], 'full', false, array(
										'class' => 'employees__photo',
										'alt'   => $employee->post_title,
									) ) ?>
									<span class="employees__link-more">Познайомитись</span>
								</div>
								<span class="heading heading--h6 employees__name"><?= $employee->post_title; ?></span>
								<span class="employees__position"><?= $position; ?></span>
							</a>
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
	<!-- employees end -->
<?php endif; ?>
