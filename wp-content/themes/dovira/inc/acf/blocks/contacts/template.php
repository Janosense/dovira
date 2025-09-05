<?php
/**
 * Contacts Block Template.
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
$text                = dovira_get_acf_field( 'text' );
$text_color          = dovira_get_acf_field( 'text_color' );
$header_text_align   = dovira_get_acf_field( 'header_text_align' );
$horizontal_align    = dovira_get_acf_field( 'horizontal_align' );
$text_align          = dovira_get_acf_field( 'text_align' );
$order               = dovira_get_acf_field( 'order' );
$view_is_row         = dovira_get_acf_field( 'view_is_row' );
$column_max_width    = dovira_get_acf_field( 'column_max_width' );
$width_ratio         = dovira_get_acf_field( 'width_ratio' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$contacts            = dovira_get_acf_field( 'contacts' );
$contacts_cities     = dovira_get_acf_field( 'contacts_cities', 'option' );
if ( ! empty( $contacts ) ) :
	$contacts = array_merge( $contacts_cities, $contacts );
else :
	$contacts = $contacts_cities;
endif;
?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- CONTACTS start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> contacts <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
		<?php if ( ! empty( $contacts ) ) : ?>
			<?php foreach ( $contacts as $contact ) : ?>
				<div
					class="wrapper contacts__wrapper contacts__wrapper--<?= $view_is_row ? 'row' : 'column'; ?> <?php if ( $view_is_row ) : ?>contacts__wrapper--<?= $width_ratio; ?> contacts__wrapper--<?= $horizontal_align; ?><?php endif; ?> contacts__wrapper--<?= $order; ?>">
					<div class="contacts__text-holder contacts__text-holder--<?= $text_align; ?>"
						 <?php if ( ! $view_is_row ) : ?>style="max-width: <?= $column_max_width; ?>%;" <?php endif; ?>>
						<?php if ( ! empty( $contact['city'] ) ) :
							if (empty($contact['heading_level'])) $contact['heading_level'] = 'h2';
							if (empty($contact['heading_style'])) $contact['heading_style'] = 'h2';
							if (empty($contact['heading_color'])) $contact['heading_color'] = '#3c3c3c';
							?>
							<?= '<' . $contact['heading_level'] . ' class="heading heading--' . $contact['heading_style'] . '" style="color: ' . $contact['heading_color'] . ';">' . $contact['city'] . '</' . $contact['heading_level'] . '>'; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['address'] ) ) : ?>
							<p class="caption contacts__address">
								<?= $contact['address']; ?>
							</p>
							<?php if ( ! empty( $contact['note'] ) ) : ?>
								<p class="contacts__note">
									<?= $contact['note']; ?>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $contact['schedule'] ) ) : ?>
								<p class="caption contacts__schedule">
									<?= $contact['schedule']; ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['phones'] ) ) :
							if ( ! is_array( $contact['phones'] ) ) :
								$phones = explode( "\r\n", $contact['phones'] ); ?>
								<div class="contacts__phones">
									<?php foreach ( $phones as $phone ) : ?>
										<a href="tel:<?= str_replace( [
											' ',
											'-',
											'(',
											')'
										], '', $phone ); ?>"><?= $phone; ?></a>
									<?php endforeach; ?>
								</div>
							<?php else :
								$phones = $contact['phones']; ?>
								<div class="contacts__phones">
									<?php foreach ( $phones as $phone ) : ?>
										<a href="tel:<?= str_replace( [
											' ',
											'-',
											'(',
											')'
										], '', $phone['number'] ); ?>"><?= $phone['number']; ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['email'] ) ) : ?>
							<div class="contacts__email">
								<a href="mailto:<?= $contact['email']; ?>"><?= $contact['email']; ?></a>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact['instagram_link'] ) ) : ?>
						<div class="contacts__instagram">
							<a href="<?= $contact['instagram_link']; ?>" target="_blank">
								<svg width="800px" height="800px" viewBox="0 0 24 24" fill="none">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6C8.68629 6 6 8.68629 6 12C6 15.3137 8.68629 18 12 18ZM12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16Z" />
									<path d="M18 5C17.4477 5 17 5.44772 17 6C17 6.55228 17.4477 7 18 7C18.5523 7 19 6.55228 19 6C19 5.44772 18.5523 5 18 5Z" />
									<path fill-rule="evenodd" clip-rule="evenodd" d="M1.65396 4.27606C1 5.55953 1 7.23969 1 10.6V13.4C1 16.7603 1 18.4405 1.65396 19.7239C2.2292 20.8529 3.14708 21.7708 4.27606 22.346C5.55953 23 7.23969 23 10.6 23H13.4C16.7603 23 18.4405 23 19.7239 22.346C20.8529 21.7708 21.7708 20.8529 22.346 19.7239C23 18.4405 23 16.7603 23 13.4V10.6C23 7.23969 23 5.55953 22.346 4.27606C21.7708 3.14708 20.8529 2.2292 19.7239 1.65396C18.4405 1 16.7603 1 13.4 1H10.6C7.23969 1 5.55953 1 4.27606 1.65396C3.14708 2.2292 2.2292 3.14708 1.65396 4.27606ZM13.4 3H10.6C8.88684 3 7.72225 3.00156 6.82208 3.0751C5.94524 3.14674 5.49684 3.27659 5.18404 3.43597C4.43139 3.81947 3.81947 4.43139 3.43597 5.18404C3.27659 5.49684 3.14674 5.94524 3.0751 6.82208C3.00156 7.72225 3 8.88684 3 10.6V13.4C3 15.1132 3.00156 16.2777 3.0751 17.1779C3.14674 18.0548 3.27659 18.5032 3.43597 18.816C3.81947 19.5686 4.43139 20.1805 5.18404 20.564C5.49684 20.7234 5.94524 20.8533 6.82208 20.9249C7.72225 20.9984 8.88684 21 10.6 21H13.4C15.1132 21 16.2777 20.9984 17.1779 20.9249C18.0548 20.8533 18.5032 20.7234 18.816 20.564C19.5686 20.1805 20.1805 19.5686 20.564 18.816C20.7234 18.5032 20.8533 18.0548 20.9249 17.1779C20.9984 16.2777 21 15.1132 21 13.4V10.6C21 8.88684 20.9984 7.72225 20.9249 6.82208C20.8533 5.94524 20.7234 5.49684 20.564 5.18404C20.1805 4.43139 19.5686 3.81947 18.816 3.43597C18.5032 3.27659 18.0548 3.14674 17.1779 3.0751C16.2777 3.00156 15.1132 3 13.4 3Z" />
								</svg>
								Instagram
							</a>
						</div>
						<?php endif; ?>
					</div>
					<div class="contacts__map-holder"
						 <?php if ( ! $view_is_row ) : ?>style="max-width: <?= $column_max_width; ?>%;" <?php endif; ?>>
						<?= $contact['map_iframe']; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<!-- CONTACTS end -->
	<script>
		(() => {
			const cta = document.querySelector('form .button');
			const formHolder = document.querySelector('.text-form__form-holder');
			if (cta) {
				cta.className = 'button button--' + formHolder.getAttribute('data-form_cta_style');
			}
		})();
	</script>
<?php endif; ?>
