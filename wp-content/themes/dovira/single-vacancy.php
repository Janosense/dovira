<?php get_header(); ?>
<main>
	<?php while ( have_posts() ) : the_post();
		$cities      = wp_get_object_terms( $post->ID, 'service-city' );
		$is_open     = dovira_get_acf_field( 'is_open' );
		$description = dovira_get_acf_field( 'description' );
		$salary_type = dovira_get_acf_field( 'salary_type' );
		$contacts    = dovira_get_acf_field( 'contacts' );

		?>
		<!-- BREADCRUMBS start -->
		<div class="breadcrumbs">
			<div class="wrapper">
				<ul class="breadcrumbs__list">
					<li class="breadcrumbs__item">
						<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
					</li>
					<li class="breadcrumbs__item">
						<a href="/vacancies/" class="breadcrumbs__link">Вакансії</a>
					</li>
					<li class="breadcrumbs__item">
						<span class="breadcrumbs__current"><?php the_title(); ?></span>
					</li>
				</ul>
			</div>
		</div>
		<!-- BREADCRUMBS end -->
		<!-- VACANCY start -->
		<?php if ( $is_open ) : ?>
			<div class="vacancy">
				<div class="wrapper vacancy__wrapper">
					<div class="vacancy__layer vacancy__info">
						<h1 class="heading heading--h1"><?php the_title(); ?></h1>
						<div class="vacancy__locations">
							<?php foreach ( $cities as $city ): ?>
								<div class="vacancy__location">
									<svg width="20" height="20" viewBox="0 0 20 20" fill="none"
										 xmlns="http://www.w3.org/2000/svg">
										<path opacity="0.4"
											  d="M17.1829 7.04183C16.3079 3.19183 12.9496 1.4585 9.99959 1.4585C9.99959 1.4585 9.99959 1.4585 9.99126 1.4585C7.04959 1.4585 3.68292 3.1835 2.80792 7.0335C1.83292 11.3335 4.46626 14.9752 6.84959 17.2668C7.73292 18.1168 8.86626 18.5418 9.99959 18.5418C11.1329 18.5418 12.2663 18.1168 13.1413 17.2668C15.5246 14.9752 18.1579 11.3418 17.1829 7.04183Z"
											  fill="#C06F94"/>
										<path
											d="M10 11.2168C11.4497 11.2168 12.625 10.0415 12.625 8.5918C12.625 7.14205 11.4497 5.9668 10 5.9668C8.55025 5.9668 7.375 7.14205 7.375 8.5918C7.375 10.0415 8.55025 11.2168 10 11.2168Z"
											fill="#C06F94"/>
									</svg>
									<?= $city->name; ?>
								</div>
							<?php endforeach; ?>
						</div>
						<?php if ( $salary_type === 'static' ) :
							$salary = dovira_get_acf_field( 'static_salary' );
							if ( ! empty( $salary ) ) :?>
								<div class="vacancy__salary vacancy__salary--desktop">
									<span
										class="vacancy__salary-value"><?= number_format( (int) $salary, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php elseif ( $salary_type === 'from' ):
							$salary_from = dovira_get_acf_field( 'salary_from' );
							if ( ! empty( $salary_from ) ) :?>
								<div class="vacancy__salary vacancy__salary--desktop">
									від <span
										class="vacancy__salary-value"><?= number_format( (int) $salary_from, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php elseif ( $salary_type === 'range' ):
							$salary_from = dovira_get_acf_field( 'salary_from' );
							$salary_to = dovira_get_acf_field( 'salary_to' );
							if ( ! empty( $salary_from ) && ! empty( $salary_to ) ) :?>
								<div class="vacancy__salary vacancy__salary--desktop">
									<span
										class="vacancy__salary-value"><?= number_format( (int) $salary_from, 0, '.', ' ' ); ?></span>
									- <span
										class="vacancy__salary-value"><?= number_format( (int) $salary_to, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contacts ) ) : ?>
							<div class="vacancy__contacts vacancy__contacts--desktop">
								<h3 class="heading heading--h5">Контакти:</h3>
								<ul class="vacancy__contacts-list">
									<?php foreach ( $contacts as $contact ) : ?>
										<li class="vacancy__contacts-item"><?= $contact['phone']; ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
					<div class="vacancy__layer vacancy__description">
						<div class="vacancy__text">
							<?= $description; ?>
						</div>
						<?php if ( $salary_type === 'static' ) :
							$salary = dovira_get_acf_field( 'static_salary' );
							if ( ! empty( $salary ) ) :?>
								<div class="vacancy__salary vacancy__salary--mobile">
									<span
										class="vacancy__salary-value"><?= number_format( (int) $salary, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php elseif ( $salary_type === 'from' ):
							$salary_from = dovira_get_acf_field( 'salary_from' );
							if ( ! empty( $salary_from ) ) :?>
								<div class="vacancy__salary vacancy__salary--mobile">
									від <span
										class="vacancy__salary-value"><?= number_format( (int) $salary_from, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php elseif ( $salary_type === 'range' ):
							$salary_from = dovira_get_acf_field( 'salary_from' );
							$salary_to = dovira_get_acf_field( 'salary_to' );
							if ( ! empty( $salary_from ) && ! empty( $salary_to ) ) :?>
								<div class="vacancy__salary vacancy__salary--mobile">
									<span
										class="vacancy__salary-value"><?= number_format( (int) $salary_from, 0, '.', ' ' ); ?></span>
									- <span
										class="vacancy__salary-value"><?= number_format( (int) $salary_to, 0, '.', ' ' ); ?></span>грн
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contacts ) ) : ?>
							<div class="vacancy__contacts vacancy__contacts--mobile">
								<h3 class="heading heading--h5">Контакти:</h3>
								<ul class="vacancy__contacts-list">
									<?php foreach ( $contacts as $contact ) : ?>
										<li class="vacancy__contacts-item"><?= $contact['phone']; ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<div class="vacancy__form-holder contacts-form7">
							<h3 class="heading heading--h5 contacts-form7__heading">Надіслати резюме:</h3>
							<?php if ( getenv( 'IS_DDEV_PROJECT' ) == 'true' ) : ?>
								<?= do_shortcode( '[contact-form-7 id="1321657" title="Форма заявки на вакансію" vacancy="' . $post->ID . '"]' ); ?>
							<?php else : ?>
								<?= do_shortcode( '[contact-form-7 id="f8f78c9" title="Форма заявки на вакансію" vacancy="' . $post->ID . '"]' ); ?>
							<?php endif; ?>
							<div class="contacts-form7__google-disclaimer">
								Цей сайт захищено reCAPTCHA, а також застосовуються <a
									href="https://policies.google.com/privacy">Політика конфіденційності</a> та <a
									href="https://policies.google.com/terms">Умови надання послуг</a> Google.
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php else: ?>
			<p>Дана вакансія більше не активна. Повернутись до <a href="/vacancies/">списку всіх відкритих вакансій</a>
			</p>
		<?php endif; ?>
		<!-- VACANCY end -->
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
