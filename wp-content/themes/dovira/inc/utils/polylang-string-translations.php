<?php

$strings = [
	'Search...',
	'Veterinary clinic "DOVIRA"',
	'Reset',
	'More',
	'Services:', // Послуг:
	'Address',
	'Schedule', //Графік роботи
	'Vetclinic', //Ветклініка
	'Pet shop', //Зоомагазин
	'Phones', //Номери телефону
	'Cookies message',
	'Show more', //Показати більше
	'Show less', //Згорнути
	'kyiv', //Згорнути
	'kharkiv', //Згорнути
];

if ( ! empty( $strings ) && function_exists( 'pll_register_string' ) ) {
	foreach ( $strings as $name => $string ) {
		pll_register_string( 'String', $string, 'dovira' );
	}
}
