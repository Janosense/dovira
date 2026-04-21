<?php
/**
 * The header.
 *
 * This is the template that displays all of the <head> section and everything up until main.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @var $args array
 *
 * @package WordPress
 * @subpackage Starter theme
 * @since 1.0.0
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap"
		rel="stylesheet">

	<!-- Google Tag Manager -->
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
			j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
			'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','GTM-53M4V7M5');</script>
	<!-- End Google Tag Manager -->

	<!-- Google tag (gtag.js) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-HKYZFG0E2W"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());

		gtag('config', 'G-HKYZFG0E2W');
	</script>
	<!-- Google tag (gtag.js) end -->


	<?php wp_head(); ?>
	<link rel="icon" type="image/png" href="/wp-content/themes/dovira/source/images/favicon/favicon-96x96.png?v=1.0.2"
		  sizes="96x96"/>
	<link rel="icon" type="image/svg+xml" href="/wp-content/themes/dovira/source/images/favicon/favicon.svg?v=1.0.2"/>
	<link rel="shortcut icon" href="/wp-content/themes/dovira/source/images/favicon/favicon.ico?v=1.0.2"/>
	<link rel="apple-touch-icon" sizes="180x180"
		  href="/wp-content/themes/dovira/source/images/favicon/apple-touch-icon.png?v=1.0.2"/>
	<meta name="apple-mobile-web-app-title" content="MyWebSite"/>
	<link rel="manifest" href="/wp-content/themes/dovira/source/images/favicon/site.webmanifest?v=1.0.2"/>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php

if ( isset( $args['mode'] ) && $args['mode'] === 'simple' ) :
	get_template_part( 'template-parts/header/site-header-simple' );
else :
	get_template_part( 'template-parts/header/site-header' );
endif; ?>
