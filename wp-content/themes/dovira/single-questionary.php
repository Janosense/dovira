<?php
if ( ! is_user_logged_in() ) :
	wp_safe_redirect( home_url() );
endif;

get_header( null, [
	'mode' => 'simple',
] );

global $post;
$admin_note       = get_field( 'admin_note', $post->ID );
$name             = get_field( 'name', $post->ID );
$phone            = get_field( 'phone', $post->ID );
$city             = get_field( 'city', $post->ID );
$email            = get_field( 'email', $post->ID );
$animal           = get_field( 'animal', $post->ID );
$pet_sex          = get_field( 'pet_sex', $post->ID );
$blood_group      = get_field( 'blood_group', $post->ID );
$pet_type         = get_field( 'pet_type', $post->ID );
$pet_old          = get_field( 'pet_old', $post->ID );
$pet_weight       = get_field( 'pet_weight', $post->ID );
$vaccination_date = get_field( 'vaccination_date', $post->ID );
$street           = get_field( 'street', $post->ID );
$cat_contact      = get_field( 'cat_contact', $post->ID );
$castration       = get_field( 'castration', $post->ID );
$donor_before     = get_field( 'donor_before', $post->ID );
$blood_take       = get_field( 'blood_take', $post->ID );
$chronic_diseases = get_field( 'chronic_diseases', $post->ID );
$pills            = get_field( 'pills', $post->ID );
$pet_name         = get_field( 'pet_name', $post->ID );

$animal_labels = [
	'dog' => __( 'Dog', 'dovira' ),
	'cat' => __( 'Cat', 'dovira' ),
];

$pet_sex_labels = [
	'female' => __( 'Female', 'dovira' ),
	'male'   => __( 'Male', 'dovira' ),
];

$blood_group_labels = [
	'all-dont-know' => __( 'Don\'t know', 'dovira' ),
	'cat-a'         => __( 'Cat - A', 'dovira' ),
	'cat-b'         => __( 'Cat - B', 'dovira' ),
	'cat-AB'        => __( 'Cat - AB', 'dovira' ),
	'dog-dea-1.1'   => __( 'Dog - DEA 1.1', 'dovira' ),
	'dog-dea-1.2'   => __( 'Dog - DEA 1.2', 'dovira' ),
	'dog-dea-1.3'   => __( 'Dog - DEA 1.3', 'dovira' ),
	'dog-dea-2'     => __( 'Dog - DEA 2', 'dovira' ),
	'dog-dea-3'     => __( 'Dog - DEA 3', 'dovira' ),
	'dog-dea-4'     => __( 'Dog - DEA 4', 'dovira' ),
	'dog-dea-5'     => __( 'Dog - DEA 5', 'dovira' ),
	'dog-dea-7'     => __( 'Dog - DEA 7', 'dovira' ),
];
?>

<main>
	<div class="questionary">
		<div class="wrapper">
			<h1 class="heading heading--h3 questionary__heading">Анкета донора</h1>
			<div class="questionary__content">
				<?php if ( ! empty( $admin_note ) ) : ?>
					<div class="questionary__row">
						<div class="questionary__item questionary__item--full">
							<span class="questionary__row-title">Примітки адміністратора:</span>
							<div class="questionary__row-value questionary__row-value--text">
								<span><?= nl2br( esc_html( $admin_note ) ); ?></span>
								<button type="button" class="questionary__copy-button"><img
										src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Owner Information -->
				<div class="questionary__section">
					<h2 class="heading heading--h4">Інформація про власника</h2>

					<div class="questionary__row">
						<div class="questionary__item">
							<span class="questionary__row-title">Імʼя:</span>
							<div class="questionary__row-value">
								<span><?= esc_html( $name ); ?></span>
								<button type="button" class="questionary__copy-button"><img
										src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
								</button>
							</div>
						</div>
						<div class="questionary__item">
							<span class="questionary__row-title">Телефон:</span>
							<div class="questionary__row-value">
								<span><?= esc_html( $phone ); ?></span>
								<button type="button" class="questionary__copy-button"><img
										src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
								</button>
							</div>
						</div>
						<div class="questionary__item">
							<span class="questionary__row-title">Місто:</span>
							<div class="questionary__row-value">
								<span><?= esc_html( $city ); ?></span>
								<button type="button" class="questionary__copy-button"><img
										src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
							</div>
						</div>
					</div>

					<?php if ( ! empty( $email ) ) : ?>
						<div class="questionary__row">
							<div class="questionary__item">
								<span class="questionary__row-title">Email:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $email ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
									</button>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Pet Information -->
				<div class="questionary__section">
					<h2 class="heading heading--h4">Інформація про тварину</h2>
					<div class="questionary__row">
						<?php if ( ! empty( $pet_name ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Клічка тварини:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $pet_name ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
									</button>
								</div>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $animal ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Вид тварини:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $animal_labels[ $animal ] ?? $animal ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
									</button>
								</div>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $pet_sex ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Стать:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $pet_sex_labels[ $pet_sex ] ?? $pet_sex ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy">
									</button>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div class="questionary__row">
						<?php if ( ! empty( $pet_type ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Порода:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $pet_type ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $pet_old ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Вік:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $pet_old ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $pet_weight ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Вага:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $pet_weight ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div class="questionary__row">
						<?php if ( ! empty( $blood_group ) ) : ?>
							<div class="questionary__item>
								<span class="questionary__row-title">Група крові:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $blood_group_labels[ $blood_group ] ?? $blood_group ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $vaccination_date ) ) : ?>
							<div class="questionary__item">
								<span class="questionary__row-title">Дата останньої вакцинації:</span>
								<div class="questionary__row-value">
									<span><?= esc_html( $vaccination_date ); ?></span>
									<button type="button" class="questionary__copy-button"><img
											src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Health & Behavior -->
				<div class="questionary__section">
					<h2 class="heading heading--h4">Здоров'я та поведінка</h2>

					<div class="questionary__row">
						<span class="questionary__row-title">Виходить на вулицю:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $street ? 'yes' : 'no'; ?>">
								<?= $street ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Контакт з вуличними котами:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $cat_contact ? 'yes' : 'no'; ?>">
								<?= $cat_contact ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Кастрація/Стерилізація:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $castration ? 'yes' : 'no'; ?>">
								<?= $castration ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Був донором раніше:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $donor_before ? 'yes' : 'no'; ?>">
								<?= $donor_before ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Отримував переливання крові:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $blood_take ? 'yes' : 'no'; ?>">
								<?= $blood_take ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Хронічні захворювання:</span>
						<div class="questionary__row-value">
							<span
								class="questionary__badge questionary__badge--<?= $chronic_diseases ? 'yes' : 'no'; ?>">
								<?= $chronic_diseases ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>

					<div class="questionary__row">
						<span class="questionary__row-title">Приймає ліки:</span>
						<div class="questionary__row-value">
							<span class="questionary__badge questionary__badge--<?= $pills ? 'yes' : 'no'; ?>">
								<?= $pills ? __( 'Так', 'dovira' ) : __( 'Ні', 'dovira' ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>
			<div class="questionary__actions">
				<button class="button button--violet" type="button" onclick="print()">Друк</button>
			</div>
		</div>
	</div>
	<span class="questionary__copy-message">
		Текст скопійовано!
	</span>
</main>

<script>
	const copy = (evt) => {
		let copyText = evt.currentTarget.previousElementSibling;
		navigator.clipboard.writeText(copyText.textContent).then(() => {
			document.querySelector(".questionary__copy-message").classList.add("questionary__copy-message--active");
			setTimeout(() => {
				document.querySelector(".questionary__copy-message").classList.remove("questionary__copy-message--active");
			}, 1000);
		})
	}
	const copyButton = document.querySelectorAll(".questionary__copy-button");
	copyButton.forEach((button) => {
		button.addEventListener("click", copy);
	});
</script>
