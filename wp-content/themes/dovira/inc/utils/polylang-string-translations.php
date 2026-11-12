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
	'DOVIRA', //Згорнути
	// Feature city-popup, the city question: keyed in Ukrainian, so an install
	// that has not translated them yet still shows Ukrainian.
	'Яке місто вас цікавить?',
	'Закрити',
	'Харків',
	'Київ',
	'Home',
	'Vacancies',
	'Services',
	'Team',
	'Job Applying', // Заявка на вакансію
	'Search', // Пошук
	'Services found', // Знайдені сервіси
	'Get a price', // Дізнатись ціну
	'Services found in', // Сервіси знайдено в
	'More', // Детальніше
	'News found', // Знайдені новини
	'News', // Новини
	// Page templates: 404, service, search, post, employee, vacancy, schema.org.
	'Oops! The tail took us the wrong way', // Ой! Хвостик заніс нас не туди
	'Looks like this page ran off for a walk.', // Схоже, ця сторінка втекла гуляти.
	'But we are nearby and will help you find the right way.', // Але ми поруч і допоможемо знайти потрібний шлях.
	'Back to the home page', // Повернутись на головну
	'Services and prices', // Послуги та вартість
	'Search services', // Пошук по послугах
	'UAH', // грн
	'We are nearby!', // Ми поруч!
	'And always happy to help you!', // І завжди раді вам допомогти!
	'Search results for', // Результати пошуку для запиту
	'Veterinary clinic', // Ветеринарна клініка
	'Branch', // Філія
	'Read more', // Читати далі
	'Pages found', // Знайдені сторінки
	'Team members found', // Знайдені люди, що створюють довіру
	'Get to know', // Познайомитись
	'Related news', // Схожі новини
	'Book an appointment', // Записатись
	'from', // від
	'Contacts', // Контакти
	'Send your CV', // Надіслати резюме
	'This vacancy is no longer active. Back to %s', // Дана вакансія більше не активна. Повернутись до %s
	'the list of all open vacancies', // списку всіх відкритих вакансій
	'This site is protected by reCAPTCHA and the Google %1$s and %2$s apply.', // Цей сайт захищено reCAPTCHA, а також застосовуються %1$s та %2$s Google.
	'Privacy Policy', // Політика конфіденційності
	'Terms of Service', // Умови надання послуг
	// ACF blocks.
	'View all documents', // Дивитись усі документи
	'Quick search of services', // Швидкий пошук по послугах
	'Learn more', // Ознайомитись
	// Block questionary (blood donor form) and its JS messages.
	'Your name', // Ваше ім’я
	'Your phone number', // Ваш номер телефону
	'Your email', // Ваш email
	'Your city', // Ваше місто
	'Animal', // Вид тварини
	'Dog', // Собака
	'Cat', // Кіт
	'Sex', // Стать
	'Female', // Самка
	'Male', // Самець
	'Blood group', // Група крові
	'Choose the blood group', // Оберіть групу крові
	'I do not know', // Не знаю
	'Pet breed', // Порода улюбленця
	'Pet age', // Вік улюбленця
	'Approximate weight', // Приблизна вага
	'Date of the last vaccination', // Дата останньої вакцинації
	'Pet name', // Клічка улюбленця
	'Goes outside', // Вихід на вулицю
	'Neutered', // Кастрація
	'Contact with cats that go outside', // Контакт із котами, які виходять на вулицю
	'Has your pet been a donor before?', // Чи доводилося Вашому улюбленцю раніше бути донором?
	'Has your pet ever had a blood transfusion?', // Чи переливали Вашому улюбленцю будь-коли кров?
	'Any chronic diseases?', // Наявність хронічних захворювань?
	'Is your pet taking any medication now?', // Чи приймає Ваш улюбленець якісь лікувальні засоби зараз?
	'Yes', // Так
	'No', // Ні
	'Sign up', // Записатись
	'Please fill in all required fields', // Будь ласка, заповніть всі обовʼязкові поля
	'Sending...', // Відправка...
	'The questionnaire has been sent!', // Анкету успішно надіслано!
	'An error occurred while sending the questionnaire', // Виникла помилка при відправці анкети
	'Could not send the questionnaire. Please try again later.', // Не вдалося відправити анкету. Спробуйте пізніше.
];

if ( ! empty( $strings ) && function_exists( 'pll_register_string' ) ) {
	foreach ( $strings as $name => $string ) {
		pll_register_string( 'String', $string, 'dovira' );
	}
}
