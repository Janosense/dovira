<?php get_header(); ?>
	<section id="sign-in"
			 class="section section--mb-none hero hero--small hero--middle"
			 style="background-color: #fff;">
		<div class="hero__wrapper hero__wrapper--center">
			<h1 class="hero__heading heading heading--h2"
				style="color: #1995ad;">Вхід</h1>
			<p class="caption" style="color: #000;">в особистий кабінет</p>
		</div>
	</section>
	<section class="section section--mb-standard">
		<div class="wrapper">
			<div class="form">
				<form class="form__form" id="sign-in">
					<div class="form__messages"></div>
					<input type="text" name="login" class="form__visually-hidden" required>
					<div class="form__row">
						<div class="form__item">
							<label for="email" class="form__label form__label--required">Email</label>
							<input type="text" id="email" name="email" class="form__textfield" required>
						</div>
						<div class="form__item">
							<label for="password" class="form__label form__label--required">Пароль</label>
							<input type="password" id="password" name="password" class="form__textfield" required>
						</div>
					</div>
					<?php wp_nonce_field( 'wp_rest' ); ?>
					<p class="form__note form__note--small">Ще не маєте акаунта? <a href="/sign-up/">Зареєструватись</a></p>
					<div class="form__actions form__actions--center">
						<button class="button button--cyan form__submit" type="button">Увійти</button>
					</div>
				</form>
			</div>
		</div>
	</section>
	<div class="loader loader--light">
		<span class="loader__component"></span>
	</div>
<?php get_footer();
