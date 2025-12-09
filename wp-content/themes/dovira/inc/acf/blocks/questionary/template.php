<?php
/**
 * Questionary Block Template.
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

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- QUESTIONARY start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> questionary <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
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
		<div class="wrapper questionary__wrapper">
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
			<div class="questionary__form-holder">
				<form action="#" class="questionary__form questionary-form">
					<?php wp_nonce_field( 'wp_rest' ); ?>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<label>
								<input type="text" name="name" class="questionary-form__text-field" required>
								<span class="questionary-form__text-field-label">Ваше ім’я</span>
							</label>
						</div>
						<div class="questionary-form__item">
							<label>
								<input type="tel" name="phone" class="questionary-form__text-field" required>
								<span class="questionary-form__text-field-label">Ваш номер телефону</span>
							</label>
						</div>
						<div class="questionary-form__item">
							<label>
								<input type="email" name="email" class="questionary-form__text-field">
								<span class="questionary-form__text-field-label">Ваш email</span>
							</label>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Вид тварини:</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="animal" value="dog" class="questionary-form__radio-field"
										   required>
									<span>Собака</span>
								</label>
								<label>
									<input type="radio" name="animal" value="cat" class="questionary-form__radio-field"
										   required>
									<span>Кіт</span>
								</label>
							</div>
						</div>
						<div class="questionary-form__item">
							<span
								class="questionary-form__item-title questionary-form__item-title--required">Стать:</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="pet-sex" value="female"
										   class="questionary-form__radio-field" required>
									<span>Самка</span>
								</label>
								<label>
									<input type="radio" name="pet-sex" value="male"
										   class="questionary-form__radio-field" required>
									<span>Самець</span>
								</label>
							</div>
						</div>
					</div>
					<div class="questionary-form__row questionary-form__row--blood-group">
						<div class="questionary-form__item">
							<label
								class="questionary-form__item-title questionary-form__item-title--select questionary-form__item-title--required"
								for="blood-group">Група крові</label>
							<select name="blood-group" id="blood-group" class="questionary-form__select-field" required
									onchange="this.classList.add('questionary-form__select-field--active')">
								<option value="all-choose" disabled selected>Оберіть групу крові</option>
								<option value="all-dont-know">Не знаю</option>
								<option value="cat-a" data-animal="cat">A</option>
								<option value="cat-b" data-animal="cat">B</option>
								<option value="cat-ab" data-animal="cat">AB</option>
								<option value="dog-dea-1.1" data-animal="dog">DEA 1.1</option>
								<option value="dog-dea-1.2" data-animal="dog">DEA 1.2</option>
								<option value="dog-dea-1.3" data-animal="dog">DEA 1.3</option>
								<option value="dog-dea-2" data-animal="dog">DEA 2</option>
								<option value="dog-dea-3" data-animal="dog">DEA 3</option>
								<option value="dog-dea-4" data-animal="dog">DEA 4</option>
								<option value="dog-dea-5" data-animal="dog">DEA 5</option>
								<option value="dog-dea-7" data-animal="dog">DEA 7</option>
							</select>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<label>
								<input type="text" name="pet-type" class="questionary-form__text-field">
								<span class="questionary-form__text-field-label">Порода улюбленця</span>
							</label>
						</div>
						<div class="questionary-form__item">
							<label>
								<input type="text" name="pet-old" class="questionary-form__text-field" required>
								<span class="questionary-form__text-field-label">Вік улюбленця</span>
							</label>
						</div>
						<div class="questionary-form__item">
							<label>
								<input type="text" name="pet-weight" class="questionary-form__text-field" required>
								<span class="questionary-form__text-field-label">Приблизна вага</span>
							</label>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<label>
								<input type="text" name="vaccination-date" class="questionary-form__text-field"
									   required>
								<span class="questionary-form__text-field-label">Дата останньої вакцинації</span>
							</label>
						</div>
						<div class="questionary-form__item">
							<label>
								<input type="text" name="pet-name" class="questionary-form__text-field">
								<span class="questionary-form__text-field-label">Клічка улюбленця</span>
							</label>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Вихід на вулицю:</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="street" value="yes" class="questionary-form__radio-field"
										   required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="street" value="no" class="questionary-form__radio-field"
										   required>
									<span>Ні</span>
								</label>
							</div>
						</div>
						<div class="questionary-form__item">
							<span
								class="questionary-form__item-title questionary-form__item-title--required">Кастрація:</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="castration" value="yes"
										   class="questionary-form__radio-field"
										   required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="castration" value="no"
										   class="questionary-form__radio-field"
										   required>
									<span>Ні</span>
								</label>
							</div>
						</div>
						<div class="questionary-form__item questionary-form__item--cat-contact" style="display: none;">
							<span class="questionary-form__item-title">Контакт із котами, які виходять на вулицю:</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="cat-contact" value="yes"
										   class="questionary-form__radio-field">
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="cat-contact" value="no"
										   class="questionary-form__radio-field">
									<span>Ні</span>
								</label>
							</div>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Чи доводилося Вашому улюбленцю раніше бути донором?</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="donor-before" value="yes"
										   class="questionary-form__radio-field"
										   required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="donor-before" value="no"
										   class="questionary-form__radio-field"
										   required>
									<span>Ні</span>
								</label>
							</div>
						</div>
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Чи переливали Вашому улюбленцю будь-коли кров?</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="blood-take" value="yes"
										   class="questionary-form__radio-field" required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="blood-take" value="no"
										   class="questionary-form__radio-field" required>
									<span>Ні</span>
								</label>
							</div>
						</div>
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Наявність хронічних захворювань?</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="chronic-diseases" value="yes"
										   class="questionary-form__radio-field"
										   required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="chronic-diseases" value="no"
										   class="questionary-form__radio-field"
										   required>
									<span>Ні</span>
								</label>
							</div>
						</div>
					</div>
					<div class="questionary-form__row">
						<div class="questionary-form__item">
							<span class="questionary-form__item-title questionary-form__item-title--required">Чи приймає Ваш улюбленець якісь лікувальні засоби зараз?</span>
							<div class="questionary-form__radio-group">
								<label>
									<input type="radio" name="pills" value="yes" class="questionary-form__radio-field"
										   required>
									<span>Так</span>
								</label>
								<label>
									<input type="radio" name="pills" value="no" class="questionary-form__radio-field"
										   required>
									<span>Ні</span>
								</label>
							</div>
						</div>
					</div>
					<button class="button button--black questionary-form__submit" type="submit">Записатись</button>
				</form>
				<div class="loader loader--light loader--fixed">
					<span class="loader__component"></span>
				</div>
			</div>
		</div>
	</section>
	<!-- QUESTIONARY end -->
	<script>
		const inputs = document.querySelectorAll('.questionary-form__text-field');
		if (inputs) {
			inputs.forEach(input => {
				if (input.value.length > 0) {
					input.classList.add('questionary-form__text-field--active');
				} else {
					input.classList.remove('questionary-form__text-field--active');
				}

				input.addEventListener('change', function () {
					if (input.value.length > 0) {
						input.classList.add('questionary-form__text-field--active');
					} else {
						input.classList.remove('questionary-form__text-field--active');
					}
				});
			});
		}

		// Blood group visibility and filtering
		const animalRadios = document.querySelectorAll('input[name="animal"]');
		const streetRadios = document.querySelectorAll('input[name="street"]');
		const bloodGroupRow = document.querySelector('.questionary-form__row--blood-group');
		const catContactItem = document.querySelector('.questionary-form__item--cat-contact');
		const bloodGroupSelect = document.getElementById('blood-group');
		const bloodGroupOptions = bloodGroupSelect.querySelectorAll('option');

		animalRadios.forEach(radio => {
			radio.addEventListener('change', function () {
				const selectedAnimal = this.value;

				// Show blood group row
				bloodGroupRow.classList.add('is-visible');

				// Reset select
				bloodGroupSelect.value = 'all-choose';
				bloodGroupSelect.classList.remove('questionary-form__select-field--active');

				// Filter options
				bloodGroupOptions.forEach(option => {
					const optionAnimal = option.dataset.animal;

					if (!optionAnimal || optionAnimal === selectedAnimal) {
						option.style.display = '';
					} else {
						option.style.display = 'none';
					}
				});

				// Show/hide cat contact item
				const streetRadiosValue = document.querySelector('input[name="street"]:checked');
				if (streetRadiosValue && streetRadiosValue.value === 'yes') {
					console.log(selectedAnimal === 'cat');
					catContactItem.style.display = (selectedAnimal === 'cat') ? 'block' : 'none';
				}
			});
		});

		streetRadios.forEach(radio => {
			radio.addEventListener('change', function () {
				const selectedValue = this.value;
				const animalRadiosValue = document.querySelector('input[name="animal"]:checked');
				if (animalRadiosValue && animalRadiosValue.value === 'cat') {
					catContactItem.style.display = selectedValue === 'yes' ? 'block' : 'none';
				}
			})
		});
	</script>
<?php endif; ?>
