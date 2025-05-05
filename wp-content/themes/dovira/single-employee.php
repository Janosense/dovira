<?php get_header(); ?>
<?php while ( have_posts() ) :
	the_post();
	global $post;
	$photo                  = dovira_get_acf_field( 'photo' );
	$position               = dovira_get_acf_field( 'position' );
	$description            = dovira_get_acf_field( 'description' );
	$quote                  = dovira_get_acf_field( 'quote' );
	$social_links           = dovira_get_acf_field( 'social_links' );
	$is_contact_cta_enabled = dovira_get_acf_field( 'is_contact_cta_enabled' );
	$title                  = dovira_get_acf_field( 'title' );
	$facts                  = dovira_get_acf_field( 'facts' );
	?>
	<!-- BREADCRUMBS start -->
	<div class="breadcrumbs">
		<div class="wrapper wrapper--tight">
			<ul class="breadcrumbs__list">
				<li class="breadcrumbs__item">
					<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
				</li>
				<li class="breadcrumbs__item">
					<a href="/team/" class="breadcrumbs__link">Люди, що створюють довіру</a>
				</li>
				<li class="breadcrumbs__item">
					<span class="breadcrumbs__current"><?php the_title(); ?></span>
				</li>
			</ul>
		</div>
	</div>
	<!-- BREADCRUMBS end -->
	<!-- EMPLOYEE start -->
	<div class="employee">
		<div class="employee__wrapper wrapper wrapper--tight">
			<div class="employee__intro">
				<?php if ( ! empty( $photo ) ) : ?>
					<?= wp_get_attachment_image( $photo['id'], 'full', false, array(
						'class' => 'employee__photo',
					) ); ?>
				<?php endif; ?>
				<div class="employee__info">
					<?php if ( ! empty( $position ) ) : ?>
						<span class="employee__position"><?= $position; ?></span>
					<?php endif; ?>
					<h1 class="employee__name heading heading--h3"><?= get_the_title(); ?></h1>
					<?php if ( ! empty( $quote ) ) : ?>
						<p class="employee__quote">
							<svg width="14" height="12" viewBox="0 0 14 12" fill="none"
								 xmlns="http://www.w3.org/2000/svg">
								<path
									d="M0 12V8.31267C0 7.28841 0.177824 6.25337 0.533471 5.20755C0.89873 4.15094 1.36972 3.16981 1.94645 2.26415C2.53278 1.34771 3.13835 0.592992 3.76313 0L6.7621 1.84367C6.24305 2.79245 5.82973 3.78437 5.52214 4.81941C5.21456 5.84366 5.06557 7.00269 5.07518 8.29649V12H0ZM7.2379 12V8.31267C7.2379 7.28841 7.41572 6.25337 7.77137 5.20755C8.13663 4.15094 8.60762 3.16981 9.18435 2.26415C9.77068 1.34771 10.3762 0.592992 11.001 0L14 1.84367C13.4809 2.79245 13.0676 3.78437 12.76 4.81941C12.4525 5.84366 12.3035 7.00269 12.3131 8.29649V12H7.2379Z"
									fill="#C06F94"/>
							</svg>
							<?= $quote; ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $social_links['facebook'] ) || ! empty( $social_links['instagram'] ) || ! empty( $social_links['linkedin'] ) ) : ?>
						<ul class="employee__social-links social-links">
							<?php if ( ! empty( $social_links['facebook'] ) ) : ?>
								<li class="social-links__item">
									<a href="<?= $social_links['facebook']; ?>" target="_blank"
									   class="social-links__link">
										<svg width="20px" height="20px" viewBox="-5 0 20 20" version="1.1"
											 xmlns="http://www.w3.org/2000/svg"
											 xmlns:xlink="http://www.w3.org/1999/xlink">
											<g id="Page-1" stroke="none" stroke-width="1" fill="none"
											   fill-rule="evenodd">
												<g id="Dribbble-Light-Preview"
												   transform="translate(-385.000000, -7399.000000)" fill="#000000">
													<g id="icons" transform="translate(56.000000, 160.000000)">
														<path
															d="M335.821282,7259 L335.821282,7250 L338.553693,7250 L339,7246 L335.821282,7246 L335.821282,7244.052 C335.821282,7243.022 335.847593,7242 337.286884,7242 L338.744689,7242 L338.744689,7239.14 C338.744689,7239.097 337.492497,7239 336.225687,7239 C333.580004,7239 331.923407,7240.657 331.923407,7243.7 L331.923407,7246 L329,7246 L329,7250 L331.923407,7250 L331.923407,7259 L335.821282,7259 Z"
															id="facebook-[#176]">

														</path>
													</g>
												</g>
											</g>
										</svg>
									</a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $social_links['instagram'] ) ) : ?>
								<li class="social-links__item">
									<a href="<?= $social_links['instagram']; ?>" target="_blank"
									   class="social-links__link">
										<svg width="20px" height="20px" viewBox="0 0 20 20" version="1.1"
											 xmlns="http://www.w3.org/2000/svg"
											 xmlns:xlink="http://www.w3.org/1999/xlink">
											<g id="Page-1" stroke="none" stroke-width="1" fill="none"
											   fill-rule="evenodd">
												<g id="Dribbble-Light-Preview"
												   transform="translate(-340.000000, -7439.000000)" fill="#000000">
													<g id="icons" transform="translate(56.000000, 160.000000)">
														<path
															d="M289.869652,7279.12273 C288.241769,7279.19618 286.830805,7279.5942 285.691486,7280.72871 C284.548187,7281.86918 284.155147,7283.28558 284.081514,7284.89653 C284.035742,7285.90201 283.768077,7293.49818 284.544207,7295.49028 C285.067597,7296.83422 286.098457,7297.86749 287.454694,7298.39256 C288.087538,7298.63872 288.809936,7298.80547 289.869652,7298.85411 C298.730467,7299.25511 302.015089,7299.03674 303.400182,7295.49028 C303.645956,7294.859 303.815113,7294.1374 303.86188,7293.08031 C304.26686,7284.19677 303.796207,7282.27117 302.251908,7280.72871 C301.027016,7279.50685 299.5862,7278.67508 289.869652,7279.12273 M289.951245,7297.06748 C288.981083,7297.0238 288.454707,7296.86201 288.103459,7296.72603 C287.219865,7296.3826 286.556174,7295.72155 286.214876,7294.84312 C285.623823,7293.32944 285.819846,7286.14023 285.872583,7284.97693 C285.924325,7283.83745 286.155174,7282.79624 286.959165,7281.99226 C287.954203,7280.99968 289.239792,7280.51332 297.993144,7280.90837 C299.135448,7280.95998 300.179243,7281.19026 300.985224,7281.99226 C301.980262,7282.98483 302.473801,7284.28014 302.071806,7292.99991 C302.028024,7293.96767 301.865833,7294.49274 301.729513,7294.84312 C300.829003,7297.15085 298.757333,7297.47145 289.951245,7297.06748 M298.089663,7283.68956 C298.089663,7284.34665 298.623998,7284.88065 299.283709,7284.88065 C299.943419,7284.88065 300.47875,7284.34665 300.47875,7283.68956 C300.47875,7283.03248 299.943419,7282.49847 299.283709,7282.49847 C298.623998,7282.49847 298.089663,7283.03248 298.089663,7283.68956 M288.862673,7288.98792 C288.862673,7291.80286 291.150266,7294.08479 293.972194,7294.08479 C296.794123,7294.08479 299.081716,7291.80286 299.081716,7288.98792 C299.081716,7286.17298 296.794123,7283.89205 293.972194,7283.89205 C291.150266,7283.89205 288.862673,7286.17298 288.862673,7288.98792 M290.655732,7288.98792 C290.655732,7287.16159 292.140329,7285.67967 293.972194,7285.67967 C295.80406,7285.67967 297.288657,7287.16159 297.288657,7288.98792 C297.288657,7290.81525 295.80406,7292.29716 293.972194,7292.29716 C292.140329,7292.29716 290.655732,7290.81525 290.655732,7288.98792"
															id="instagram-[#167]">

														</path>
													</g>
												</g>
											</g>
										</svg>
									</a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $social_links['linkedin'] ) ) : ?>
								<li class="social-links__item">
									<a href="<?= $social_links['linkedin']; ?>" target="_blank"
									   class="social-links__link">
										<svg width="20px" height="20px" viewBox="0 0 20 20" version="1.1"
											 xmlns="http://www.w3.org/2000/svg"
											 xmlns:xlink="http://www.w3.org/1999/xlink">
											<g id="Page-1" stroke="none" stroke-width="1" fill="none"
											   fill-rule="evenodd">
												<g id="Dribbble-Light-Preview"
												   transform="translate(-180.000000, -7479.000000)" fill="#000000">
													<g id="icons" transform="translate(56.000000, 160.000000)">
														<path
															d="M144,7339 L140,7339 L140,7332.001 C140,7330.081 139.153,7329.01 137.634,7329.01 C135.981,7329.01 135,7330.126 135,7332.001 L135,7339 L131,7339 L131,7326 L135,7326 L135,7327.462 C135,7327.462 136.255,7325.26 139.083,7325.26 C141.912,7325.26 144,7326.986 144,7330.558 L144,7339 L144,7339 Z M126.442,7323.921 C125.093,7323.921 124,7322.819 124,7321.46 C124,7320.102 125.093,7319 126.442,7319 C127.79,7319 128.883,7320.102 128.883,7321.46 C128.884,7322.819 127.79,7323.921 126.442,7323.921 L126.442,7323.921 Z M124,7339 L129,7339 L129,7326 L124,7326 L124,7339 Z"
															id="linkedin-[#161]">

														</path>
													</g>
												</g>
											</g>
										</svg>
									</a>
								</li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $is_contact_cta_enabled ) : ?>
						<a href="/contacts/" class="button button--black">Записатись</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="employee__content">
				<h3 class="heading heading--h4"><?= $title; ?></h3>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="employee__description">
						<?= $description; ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $facts ) ) : ?>
					<div class="employee__facts">
						<?php foreach ( $facts as $fact ) : ?>
							<div class="employee__fact">
								<p class="heading heading--h6 employee__fact-title"><?= $fact['title']; ?></p>
								<p class="employee__fact-text"><?= $fact['fact']; ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<!-- EMPLOYEE end -->
	<?php the_content(); ?>
<?php endwhile; ?>
<?php get_footer(); ?>

