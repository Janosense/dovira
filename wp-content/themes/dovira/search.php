<?php get_header(); ?>
	<main>
		<div class="search-results">

			<!-- BREADCRUMBS start -->
			<div class="breadcrumbs">
				<div class="wrapper">
					<ul class="breadcrumbs__list">
						<li class="breadcrumbs__item">
							<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
						</li>
						<li class="breadcrumbs__item">
							<span class="breadcrumbs__current">Пошук</span>
						</li>
					</ul>
				</div>
			</div>
			<!-- BREADCRUMBS end -->
			<?php
			$search_query_var = get_query_var( 's' );
			global $posts;
			$sub_services = [];
			$services     = get_posts( array(
				'numberposts' => - 1,
				'post_type'   => 'service',
			) );

			if ( ! empty( $services ) ) :
				foreach ( $services as $service ) :
					$prices = dovira_get_acf_field( 'prices', $service->ID );
					if ( ! empty( $prices ) ) :
						foreach ( $prices as $price ) :
							similar_text( strtolower( $price['title'] ), strtolower( $search_query_var ), $percent );
							if ( str_contains( strtolower( $price['title'] ), strtolower( $search_query_var ) ) || $percent >= 70 ) :
								$sub_services[] = $price;
							endif;
						endforeach;
					endif;
				endforeach;
			endif;

			$search_posts  = [];
			$posts_by_meta = get_posts( [
				'numberposts' => - 1,
				'post_type'   => 'any',
				'meta_query'  => [
					'relation' => 'OR',
					[
						'key'     => 'key_words',
						'value'   => $search_query_var,
						'compare' => 'LIKE',
					],
					[
						'key'     => 'key_words_services',
						'value'   => $search_query_var,
						'compare' => 'LIKE',
					]
				],
			] );

			if ( ! empty( $posts ) || ! empty( $posts_by_meta ) ) :
				foreach ( $posts as $post_item ) :
					if ( ! isset( $search_posts[ $post_item->post_type ][ $post_item->ID ] ) ) {
						$search_posts[ $post_item->post_type ][ $post_item->ID ] = $post_item;
					}
				endforeach;
				foreach ( $posts_by_meta as $post_item ) :
					if ( ! isset( $search_posts[ $post_item->post_type ][ $post_item->ID ] ) ) {
						$search_posts[ $post_item->post_type ][ $post_item->ID ] = $post_item;
					}
				endforeach;
			endif; ?>

			<div class="section section--mb-standard">
				<div class="wrapper">
					<div class="section__header">
						<h2 class="heading heading--h3 search-results__heading">Результати пошуку для запиту:
							<span><?= $search_query_var; ?></span></h2>
					</div>
					<?php if ( ! empty( $sub_services ) ) :
						$cities = get_terms( array(
							'taxonomy'   => 'service-city',
							'hide_empty' => false,
						) ); ?>
						<div class="search-results__holder">
							<h3 class="heading heading--h5 search-results__subheading">Знайдені сервіси:</h3>
							<ul class="search-results__sub-services">
								<?php foreach ( $sub_services as $sub_service ) : ?>
									<li class="search-results__sub-service sub-service">
										<div class="sub-service__description">
											<h3 class="heading heading--h5"><?= $sub_service['title']; ?></h3>
											<p class="text"><?= $sub_service['description']; ?></p>
											<?php if ( isset( $sub_service['linked_page'] ) && ! empty( $sub_service['linked_page'] ) ) : ?>
												<a href="<?= $sub_service['linked_page']['url']; ?>"
												   class="sub-service__linked-page"
												   target="<?= $sub_service['linked_page']['target']; ?>">
													<?= $sub_service['linked_page']['title']; ?>
												</a>
											<?php endif; ?>
										</div>
										<div class="sub-service__prices">
											<?php foreach ( $cities as $city ) : ?>
												<div class="sub-service__price">
													<span class="sub-service__price-city"><?= $city->name; ?></span>
													<?php $price = $sub_service['is_price_general'] ? $sub_service['price'] : $sub_service['city_prices'][ 'price_city_' . $city->term_id ];
													if ( empty( $price ) ) :
														$contacts = dovira_get_acf_field( 'contacts_cities', 'option' );
														$phone   = '';
														if ( ! empty( $contacts ) ) :
															foreach ( $contacts as $contact ) :
																if ( $contact['city'] === $city->name ) :
																	$phone = $contact['phones'][0]['number'];
																	break;
																endif;
															endforeach;
														endif; ?>
														<div class="sub-service__price-empty">Дізнатись ціну:<br>
															<span><?= $phone; ?></span></div>
													<?php else: ?>
														<div class="sub-service__price-amount">
															<?= $price; ?>
															<span>грн</span>
														</div>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $search_posts['service'] ) ): ?>
						<div class="search-results__holder">
							<h3 class="heading heading--h5 search-results__subheading">Сервіси знайдено в:</h3>
							<ul class="services__list services__list--3">
								<?php foreach ( $search_posts['service'] as $service ):
									if ( $service->post_parent === 0 ) :
										$cover = dovira_get_acf_field( 'image', $service->ID );
										$prices = dovira_get_acf_field( 'prices', $service->ID ); ?>
										<li class="services__item"
											data-search="<?= mb_strtolower( $service->post_title, 'UTF-8' );; ?>">
											<a href="<?= get_permalink( $service->ID ); ?>" class="services__link">
												<?= wp_get_attachment_image( $cover['id'], 'full', false, array(
													'class' => 'services__image',
													'alt' => 'Ветеринарна клініка: ' . $service->post_title,
												) ); ?>
												<span
													class="services__counter">Послуг: <?= is_countable( $prices ) ? count( $prices ) : 0 ?></span>
												<h3 class="heading heading--h6 services__title"><?= $service->post_title; ?></h3>
												<span
													class="button button--black-border services__cta">Детальніше</span>
											</a>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $search_posts['post'] ) ): ?>
						<div class="search-results__holder">
							<h3 class="heading heading--h5 search-results__subheading">Знайдені новини:</h3>
							<ul class="news__list news__list--3">
								<?php foreach ( $search_posts['post'] as $article ) : ?>
									<li class="news__item">
										<a href="<?= get_permalink( $article->ID ); ?>" class="news__link">
											<div class="news__thumbnail-holder">
												<?= get_the_post_thumbnail( $article->ID, 'full', array(
													'class' => 'news__thumbnail',
													'alt' => get_the_title( $article->ID ),
												) ) ?>
												<span class="news__link-more">Читати далі</span>
											</div>
											<h3 class="heading heading--h6 news__heading"><?= get_the_title( $article->ID ); ?></h3>
											<?php if ( ! empty( get_the_excerpt( $article->ID ) ) ) : ?>
												<p class="news__excerpt"><?= get_the_excerpt( $article->ID ); ?></p>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $search_posts['page'] ) ): ?>
						<div class="search-results__holder">
							<h3 class="heading heading--h5 search-results__subheading">Знайдені сторінки:</h3>
							<ul class="search-results__list">
								<?php foreach ( $search_posts['page'] as $page ) : ?>
									<li class="search-results__item">
										<a href="<?= get_permalink( $page->ID ); ?>"
										   class="heading heading--h6 search-results__link"><?= $page->post_title; ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $search_posts['employee'] ) ): ?>
						<div class="search-results__holder">
							<h3 class="heading heading--h5 search-results__subheading">Знайдені люди, що створюють
								довіру:</h3>
							<ul class="employees__grid employees__grid--5">
								<?php foreach ( $search_posts['employee'] as $employee ) :
									$photo = dovira_get_acf_field( 'photo', $employee->ID );
									$position = dovira_get_acf_field( 'position', $employee->ID ); ?>
									<li class="employees__item">
										<a href="<?= get_permalink( $employee->ID ); ?>" class="employees__link">
											<div class="employees__photo-holder">
												<?= wp_get_attachment_image( $photo['id'], 'full', false, array(
													'class' => 'employees__photo',
													'alt' =>  $employee->post_title,
												) ) ?>
												<span class="employees__link-more">Познайомитись</span>
											</div>
											<span
												class="heading heading--h6 employees__name"><?= $employee->post_title; ?></span>
											<span class="employees__position"><?= $position; ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</main>
<?php get_footer();
