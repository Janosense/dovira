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
		}, 10000);
	}
};

const validateRequiredTextFields = (form) => {
	let isValid = true;
	const textFields = form.querySelectorAll('.questionary-form__text-field[required]');

	textFields.forEach((field) => {
		const formItem = field.closest('.questionary-form__item');
		if (field.value.trim() === '') {
			formItem.classList.add('questionary-form__item--error');
			isValid = false;
		}
	});

	return isValid;
};

const validateRequiredSelectFields = (form) => {
  let isValid = true;
  const selectFields = form.querySelectorAll('.questionary-form__select-field[required]');

  selectFields.forEach((field) => {
    const formItem = field.closest('.questionary-form__item');
    if (field.value.trim() === '' || field.value === 'all-choose' || field.value === 'choose') {
      formItem.classList.add('questionary-form__item--error');
      isValid = false;
    }
  });

  return isValid;
};

const validateRequiredRadioGroups = (form) => {
	let isValid = true;
	const radioGroups = {};

	// Collect all required radio buttons
	const requiredRadios = form.querySelectorAll('.questionary-form__radio-field[required]');

	requiredRadios.forEach((radio) => {
		const name = radio.getAttribute('name');
		if (!radioGroups[name]) {
			radioGroups[name] = {
				checked: false,
				formItem: radio.closest('.questionary-form__item')
			};
		}
		if (radio.checked) {
			radioGroups[name].checked = true;
		}
	});

	// Check if each group has a selection
	Object.values(radioGroups).forEach((group) => {
		if (!group.checked) {
			group.formItem.classList.add('questionary-form__item--error');
			isValid = false;
		}
	});

	return isValid;
};

const removeErrorClass = (element) => {
	const formItem = element.closest('.questionary-form__item');
	if (formItem && formItem.classList.contains('questionary-form__item--error')) {
		formItem.classList.remove('questionary-form__item--error');
	}
};

const clearAllErrors = (form) => {
	const errorItems = form.querySelectorAll('.questionary-form__item--error');
	errorItems.forEach((item) => {
		item.classList.remove('questionary-form__item--error');
	});
};

const questionaryFormHandler = () => {
	const form = document.querySelector('.questionary-form');
	const loader = document.querySelector('.loader');

	if (!form) {
		return;
	}

  const submitButton = form.querySelector('.questionary-form__submit');

	// Add event listeners to text fields to remove error class on input
	const textFields = form.querySelectorAll('.questionary-form__text-field');
	textFields.forEach((field) => {
		field.addEventListener('input', () => {
			removeErrorClass(field);
		});
	});

	// Add event listeners to radio buttons to remove error class on change
	const radioFields = form.querySelectorAll('.questionary-form__radio-field');
	radioFields.forEach((radio) => {
		radio.addEventListener('change', () => {
			removeErrorClass(radio);
		});
	});

  // Add event listeners to select fields to remove error class on select
  const selectFields = form.querySelectorAll('.questionary-form__select-field');
  selectFields.forEach((field) => {
    field.addEventListener('change', () => {
      removeErrorClass(field);
    });
  });

  submitButton.addEventListener('click', async (event) => {
		event.preventDefault();

		// Clear previous errors and messages
		clearAllErrors(form);

		// Validate form
		const isTextFieldsValid = validateRequiredTextFields(form);
		const isRadioGroupsValid = validateRequiredRadioGroups(form);
		const isSelectFieldsValid = validateRequiredSelectFields(form);

		if (!isTextFieldsValid || !isRadioGroupsValid || !isSelectFieldsValid) {
			showMessage(form, 'Будь ласка, заповніть всі обовʼязкові поля', 'error');

			// Scroll to first error
			const firstError = form.querySelector('.questionary-form__item--error');
			if (firstError) {
				firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
			return;
		}

		loader.classList.add('loader--active');

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
				showMessage(form, 'Анкету успішно надіслано!', 'success');

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
