const NAMESPACE = 'dovira/v1';
const REST_BASE = 'questionary';
const REST_BASE_PATH = `/wp-json/${NAMESPACE}/${REST_BASE}`;

const showMessage = (container, message, type = 'success') => {
	// Remove any existing messages
	const existingMessage = container.querySelector('.questionary-form__message');
	if (existingMessage) {
		existingMessage.remove();
	}

	// Create message element
	const messageElement = document.createElement('div');
	messageElement.className = `questionary-form__message questionary-form__message--${type}`;
	messageElement.textContent = message;

	// Insert message at the beginning of the form
	container.insertAdjacentElement('afterbegin', messageElement);

	// Scroll to message
	messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

	// Auto-remove success message after 5 seconds
	if (type === 'success') {
		setTimeout(() => {
			messageElement.remove();
		}, 5000);
	}
};

const questionaryFormHandler = () => {
	const form = document.querySelector('.questionary-form');
  const loader = document.querySelector('.loader');

	if (!form) {
		return;
	}

	form.addEventListener('submit', async (event) => {
    loader.classList.add('loader--active');
		event.preventDefault();

		const submitButton = form.querySelector('.questionary-form__submit');
		const formData = new FormData(form);

		// Disable submit button during request
		submitButton.disabled = true;
		submitButton.textContent = 'Відправка...';

		try {
			const response = await fetch(`${REST_BASE_PATH}/save`, {
				method: 'POST',
				body: formData
			});

			const data = await response.json();

			if (response.ok && data.success) {
				// Success
				showMessage(form, data.message || 'Анкету успішно надіслано!', 'success');

				// Reset form
				form.reset();

				// Remove active classes from text fields
				const textFields = form.querySelectorAll('.questionary-form__text-field');
				textFields.forEach(field => {
					field.classList.remove('questionary-form__text-field--active');
				});
        loader.classList.remove('loader--active');
			} else {
				// Error from API
				const errorMessage = data.message || 'Виникла помилка при відправці анкети';
				showMessage(form, errorMessage, 'error');
        loader.classList.remove('loader--active');
			}
		} catch (error) {
			// Network or other error
			console.error('Error submitting questionary:', error);
			showMessage(form, 'Не вдалося відправити анкету. Спробуйте пізніше.', 'error');
		} finally {
			// Re-enable submit button
			submitButton.disabled = false;
			submitButton.textContent = 'Записатись';
      loader.classList.remove('loader--active');
		}
	});
};

export { questionaryFormHandler };
