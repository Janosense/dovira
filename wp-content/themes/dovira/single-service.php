<?php get_header(); ?>
<?php while ( have_posts() ) :
	the_post();
	$cities      = get_terms( array(
		'taxonomy'   => 'service-city',
		'hide_empty' => false,
	) );
	$description = dovira_get_acf_field( 'description' );
	$prices      = dovira_get_acf_field( 'prices' );
	$prices_data = array();

	if ( ! is_wp_error( $cities ) && ! empty( $cities ) && ! empty( $prices ) ) {
		foreach ( $cities as $city ) {
			$prices_data[ 'city_' . $city->term_id ] = array(
				'city'     => dovira_translate_string( $city->name ),
				'services' => array(),
			);
		}

		foreach ( $prices as $price ) {
			foreach ( $price['cities'] as $city ) {
				if ( isset( $prices_data[ $city ] ) ) {
					$prices_data[ $city ]['services'][] = $price;
				}
			}
		}
	} ?>
	<!-- BREADCRUMBS start -->
	<div class="breadcrumbs">
		<div class="wrapper wrapper--tight">
			<ul class="breadcrumbs__list">
				<li class="breadcrumbs__item">
					<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
				</li>
				<li class="breadcrumbs__item">
					<a href="/services/" class="breadcrumbs__link">Послуги</a>
				</li>
				<li class="breadcrumbs__item">
					<span class="breadcrumbs__current"><?php the_title(); ?></span>
				</li>
			</ul>
		</div>
	</div>
	<!-- BREADCRUMBS end -->
	<!-- SERVICE start -->
	<div class="section section--mb-standard service">
		<div class="wrapper wrapper--tight">
			<header class="section__header">
				<h1 class="heading heading--h1"><?php the_title(); ?></h1>
				<?php if ( ! empty( $description ) ) : ?>
					<div class="text"><?= $description; ?></div>
				<?php endif; ?>
			</header>
			<?php if ( ! empty( $prices_data ) ) : ?>
				<div class="service__prices">
					<div class="service__header">
						<h2 class="heading heading--h3 service__heading">Послуги та вартість</h2>
						<div class="service__search">
							<label class="service__search-field">
								<input type="text" id="service-search-input"
									   placeholder="Пошук по послугах:">
								<button type="reset" id="service-search-reset">Скинути
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
					</div>
					<div class="service__prices-toggles">
						<?php
						$toggles_index = 0;
						foreach ( $prices_data as $city_price ) : ?>
							<?php if ( ! empty( $city_price['services'] ) ) : ?>
								<button type="button"
										class="service__prices-toggle <?php if ( $toggles_index === 0 ): ?>service__prices-toggle--active<?php endif; ?>"><?= $city_price['city']; ?></button>
								<?php $toggles_index ++;
							endif; ?>
						<?php endforeach; ?>
					</div>
					<div class="service__prices-holder">
						<?php
						$lists_index = 0;
						foreach ( $prices_data as $city => $city_price ) : ?>
							<?php if ( ! empty( $city_price['services'] ) ) : ?>
								<ul class="service__price-list <?php if ( $lists_index === 0 ): ?>service__price-list--active<?php endif; ?>"
									id="<?= $city; ?>">
									<?php foreach ( $city_price['services'] as $service ) : ?>
										<li class="service__price-item"
											data-search="<?= mb_strtolower( $service['title'] ); ?><?= mb_strtolower( $service['description'] ); ?>">
											<div class="service__price-description">
												<h3 class="heading heading--h5"><?= $service['title']; ?></h3>
												<p class="text"><?= $service['description']; ?></p>
												<?php if ( isset( $service['linked_page'] ) && ! empty( $service['linked_page'] ) ) : ?>
													<a href="<?= $service['linked_page']['url']; ?>"
													   class="service__linked-page"
													   target="<?= $service['linked_page']['target']; ?>">
														<?= $service['linked_page']['title']; ?>
													</a>
												<?php endif; ?>
											</div>
											<?php $price = $service['is_price_general'] ? $service['price'] : $service['city_prices'][ 'price_' . $city ];
											if ( empty( $price ) ) :
												$contacts = dovira_get_acf_field( 'contacts_cities', 'option' );
												$phone   = '';
												if ( ! empty( $contacts ) ) :
													foreach ( $contacts as $contact ) :
														if ( $contact['city'] === $city_price['city'] ) :
															$phone = $contact['phones'][0]['number'];
															break;
														endif;

													endforeach;
												endif; ?>
												<div class="service__price-empty">Дізнатись ціну: <span><?= $phone; ?></span></div>
											<?php else: ?>
												<div class="service__price-amount">
													<?= $price; ?>
													<span>грн</span>
												</div>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
								<?php $lists_index ++;
							endif; ?>
						<?php endforeach; ?>
						<div class="loader loader--light loader--absolute">
							<span class="loader__component"></span>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<!-- SERVICE end -->
	<?php the_content(); ?>
	<?php get_template_part( 'template-parts/seo-description' ); ?>
<?php endwhile; ?>
<?php get_footer(); ?>

