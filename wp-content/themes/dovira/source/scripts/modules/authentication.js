const NAMESPACE = 'dovira/v1';
const REST_BASE = 'user';
const REST_BASE_PATH = `/wp-json/${NAMESPACE}/${REST_BASE}`;
const CHECK_SPAM_FIELD = 'login';
const messages = [];

const addMessage = (message, type) => {
  messages.unshift({
    type,
    text: message
  });
};

const clearMessages = () => {
  while (messages.length > 0) {
    messages.pop();
  }

  const messagesContainer = document.querySelector('.form__messages');
  messagesContainer.innerHTML = '';
}

const showMessages = () => {
  const messagesContainer = document.querySelector('.form__messages');
  if (messagesContainer) {
    messages.forEach((message) => {
      const messageContainer = document.createElement('p');
      messageContainer.classList.add('form__message', `form__message--${message.type}`)
      messageContainer.textContent = message.text;
      messagesContainer.insertAdjacentElement('afterbegin', messageContainer);
    });
  }
}

const fetchData = async (data, endpoint) => {
  const response = await fetch(`${REST_BASE_PATH}/${endpoint}/`, {
    method: 'POST',
    body: data
  })
  if (response.ok) {
    return await response.json();
  }
}

const validateEmail = (email) => {
  return String(email)
    .toLowerCase()
    .match(
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
    )
};

const checkRequiredTextFields = (form) => {
  let isValid = true;
  [...form.elements].forEach((element) => {
    if ((element.type === 'text' || element.type === 'email' || element.type === 'password')
      && element.required
      && element.name !== CHECK_SPAM_FIELD
      && element.value === '') {

      element.parentElement.classList.add('form__item--error');
      isValid = false;
    }
  });

  return isValid;
}

const userSignUp = () => {
  const form = document.querySelector('form#sign-up');
  const loader = document.querySelector('.loader');
  if (form) {
    [...form.elements].forEach((element) => {
      element.addEventListener('input', () => {
        if (element.parentElement.classList.contains('form__item--error')) {
          element.parentElement.classList.remove('form__item--error')
        }
      });
    });

    const submitButton = form.querySelector('button.form__submit');
    if (submitButton) {
      submitButton.addEventListener('click', () => {
        loader.classList.add('loader--active');
        clearMessages();
        let isFormValid = true;

        const isRequiredFieldsFilled = checkRequiredTextFields(form);
        if (!isRequiredFieldsFilled) {
          addMessage('Обовʼзкові поля не заповнені', 'error');
          isFormValid = false;
        }

        const isEmailValid = validateEmail(form.elements.email.value);
        if (!isEmailValid) {
          form.elements.email.parentElement.classList.add('form__item--error');
          addMessage('Введено некоректний Email', 'error');
          isFormValid = false;
        }

        const isPasswordLengthValid = form.elements.password.value.length >= 6;
        if (!isPasswordLengthValid) {
          form.elements.password.parentElement.classList.add('form__item--error');
          form.elements.password_repeat.parentElement.classList.add('form__item--error');
          addMessage('Пароль має містити 6 і більше символів', 'error');
          isFormValid = false;
        }

        const isPasswordsMatch = form.elements.password.value === form.elements.password_repeat.value;
        if (!isPasswordsMatch) {
          form.elements.password.parentElement.classList.add('form__item--error');
          form.elements.password_repeat.parentElement.classList.add('form__item--error');
          addMessage('Паролі не співпадають', 'error');
          isFormValid = false;
        }

        if (!isFormValid) {
          setTimeout(() => {
            showMessages();
            window.scrollTo({
              top: 0,
              left: 0,
              behavior: "smooth"
            });
            loader.classList.remove('loader--active');
          }, 1000);
        } else {
          fetchData(new FormData(form), 'sign-up').then((response) => {
            if (!response.success) {
              addMessage(response.data.message, 'error');

              setTimeout(() => {
                showMessages();
                window.scrollTo({
                  top: 0,
                  left: 0,
                  behavior: "smooth"
                });
                loader.classList.remove('loader--active');
              }, 1000);

              if (response.data.error_type === 'email_registered') {
                form.elements.email.parentElement.classList.add('form__item--error');
              }
            } else {

              setTimeout(() => {
                const formHolder = document.querySelector('.form');
                const responseTemplate = document.querySelector('template#response');
                const templateContent = responseTemplate.content.cloneNode(true);
                templateContent.querySelector('h2.heading span').textContent = form.elements.check_spam_field.value;

                formHolder.innerHTML = '';
                formHolder.append(templateContent);

                window.scrollTo({
                  top: 0,
                  left: 0,
                  behavior: "smooth"
                });
                loader.classList.remove('loader--active');
              }, 1000);
            }
          });
        }
      });
    }

    form.addEventListener('submit', (evt) => {
      evt.preventDefault();
    });
  }
}

const userSignIn = () => {
  const form = document.querySelector('form#sign-in');
  const loader = document.querySelector('.loader');
  if (form) {
    const submitButton = form.querySelector('button.form__submit');
    if (submitButton) {

      [...form.elements].forEach((element) => {
        element.addEventListener('input', () => {
          if (element.parentElement.classList.contains('form__item--error')) {
            element.parentElement.classList.remove('form__item--error')
          }
        });
      });

      submitButton.addEventListener('click', () => {
        loader.classList.add('loader--active');
        clearMessages();
        let isFormValid = true;

        const isRequiredFieldsFilled = checkRequiredTextFields(form);
        if (!isRequiredFieldsFilled) {
          addMessage('Обовʼзкові поля не заповнені', 'error');
          isFormValid = false;
        }

        const isEmailValid = validateEmail(form.elements.email.value);
        if (!isEmailValid) {
          form.elements.email.parentElement.classList.add('form__item--error');
          addMessage('Введено некоректний Email', 'error');
          isFormValid = false;
        }

        if (!isFormValid) {
          setTimeout(() => {
            showMessages();
            window.scrollTo({
              top: 0,
              left: 0,
              behavior: "smooth"
            });
            loader.classList.remove('loader--active');
          }, 1000);
        } else {
          fetchData(new FormData(form), 'sign-in').then((response) => {
            if (!response.success) {
              addMessage(response.data.message, 'error');

              setTimeout(() => {
                showMessages();
                window.scrollTo({
                  top: 0,
                  left: 0,
                  behavior: "smooth"
                });
                loader.classList.remove('loader--active');
              }, 1000);

              if (response.data.error_type === 'email_registered') {
                form.elements.email.parentElement.classList.add('form__item--error');
              }
            } else {
              window.location.assign('/testing/');
            }
          });
        }
      });
    }

    form.addEventListener('submit', (evt) => {
      evt.preventDefault();
    });
  }
}

const authentication = () => {
  userSignUp();
  userSignIn();
}

export {authentication}
