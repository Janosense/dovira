<?php
/**
 * The header.
 *
 * This is the template that displays all of the <head> section and everything up until main.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
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
	<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

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
<?php get_template_part( 'template-parts/header/site-header' ); ?>
