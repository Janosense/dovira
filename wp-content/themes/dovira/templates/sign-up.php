<?php get_header(); ?>

	<section id="sign-in"
			 class="section section--mb-none hero hero--small hero--middle		"
			 style="background-color: #fff;">
		<div class="hero__wrapper hero__wrapper--center">
			<h1 class="hero__heading heading heading--h2"
				style="color: #1995ad;">Реєстрація</h1>
			<p class="caption" style="color: #000;">Отримайте доступ до тестування, статистики і можливість пройти іспит
				в нашій школі</p>
		</div>
	</section>
	<section class="section section--mb-standard"
			 style="background-color: #FFF;">
		<div class="wrapper">
			<div class="form form--500">
				<form class="form__form" id="sign-up">
					<div class="form__messages"></div>
					<input type="text" name="login" class="form__visually-hidden" required>
					<div class="form__row">
						<div class="form__item form__item--full-width">
							<label for="email" class="form__label form__label--required">Email</label>
							<input type="email" id="email" name="email" class="form__textfield" required>
							<span class="form__field-note">Email буде використовуватись для подальшого входу в систему школи</span>
						</div>
					</div>
					<div class="form__row">
						<div class="form__item">
							<label for="check_spam_field" class="form__label form__label--required">Імʼя</label>
							<input type="text" id="check_spam_field" name="check_spam_field" class="form__textfield"
								   required>
						</div>
						<div class="form__item">
							<label for="last_name" class="form__label form__label--required">Призвіще</label>
							<input type="text" id="last_name" name="last_name" class="form__textfield" required>
						</div>
					</div>
					<div class="form__row">
						<div class="form__item">
							<label for="password" class="form__label form__label--required">Пароль</label>
							<input type="password" id="password" name="password" class="form__textfield" required
								   autocomplete="new-password">
							<span class="form__field-note">Підберіть надійний пароль від 6 символів</span>
						</div>
						<div class="form__item">
							<label for="password_repeat" class="form__label form__label--required">Підтвердіть
								пароль</label>
							<input type="password" id="password_repeat" name="password_repeat" class="form__textfield"
								   required autocomplete="new-password">
							<span class="form__field-note">Паролі повинні співпадати</span>
						</div>
					</div>
					<?php wp_nonce_field( 'wp_rest' ); ?>
					<p class="form__note">Реєструючись на нашому сайті Ви даєте згоду на обробку персональної інформації.</p>
					<p class="form__note form__note--small">Вже маєте акаунт? <a href="/sign-in/">Увійти</a></p>
					<div class="form__actions form__actions--center">
						<button class="button button--cyan form__submit" type="button">Зареєструватись</button>
					</div>
				</form>
			</div>
		</div>
	</section>
	<div class="loader loader--light">
		<span class="loader__component"></span>
	</div>

<template id="response">
	<div class="form__response">
		<h2 class="heading heading--h5" style="color: #1995ad;">Вітаємо, <span></span>!</h2>
		<p class="form__note">Тепер Ви можете увійти у свій кабінет використовуючи дані для входу</p>
		<a href="/sign-in/" class="button button--cyan">Увійти</a>
	</div>
</template>

<?php get_footer();
