const NAMESPACE = 'dovira/v1';
const REST_BASE = 'test';
const REST_BASE_PATH = `/wp-json/${NAMESPACE}/${REST_BASE}`;

const fetchData = async (data) => {
  const response = await fetch(`${REST_BASE_PATH}/check-test/`, {
    method: 'POST',
    body: data
  })
  if (response.ok) {
    return await response.json();
  }
}

const inputOnClickHandler = (evt) => {
  const question = evt.currentTarget.parentElement.parentElement.parentElement.parentElement;
  question.classList.remove('questions__item--empty');
  const inputs = question.querySelectorAll('input');
  inputs.forEach((input) => {
    input.removeEventListener('click', inputOnClickHandler);
  });
}

const checkTest = async () => {
  const form = document.querySelector('form#test');
  const loader = document.querySelector('.loader');
  if (form) {
    form.addEventListener('submit', (evt) => {
      evt.preventDefault();
      loader.classList.add('loader--active');
      const questions = document.querySelectorAll('.questions__item');
      let allAnswersFiled = true;
      if (questions.length) {
        questions.forEach((question) => {
          const inputs = question.querySelectorAll('input');
          let inputsGroupChecked = false;
          inputs.forEach((input) => {
            if (input.checked) {
              inputsGroupChecked = true;
            }
          });

          if (!inputsGroupChecked) {
            allAnswersFiled = false;
            question.classList.add('questions__item--empty');

            inputs.forEach((input) => {
              input.addEventListener('click', inputOnClickHandler)
            });
            loader.classList.remove('loader--active');
          }
        });
      }
      if (allAnswersFiled) {
        fetchData(new FormData(form)).then((response) => {
          if (response.success && response.data.length === questions.length) {
            const submitButton = document.querySelector('.questions__submit');
            submitButton.disabled = true;

            const inputs = document.querySelectorAll('form input');
            inputs.forEach((input) => {
              input.disabled = true;
            });

            let correctAnswers = 0
            questions.forEach((question, index) => {
              if (response.data[index].is_answer_correct) {
                question.classList.add('questions__item--correct');
                correctAnswers++;
              } else {
                question.classList.add('questions__item--error');
                const inputs = question.querySelectorAll('input');
                inputs[response.data[index].correct_answer].classList.add('answers__correct-answer')
              }
            });

            const questionsResponse = document.querySelector('.questions__response');
            if (questionsResponse) {
              const questionsResponseValue = questionsResponse.querySelector('.questions__response-value');
              questionsResponseValue.textContent = `${correctAnswers}/${questions.length}`;
              questionsResponse.classList.add('questions__response--visible');
            }

            setTimeout(() => {
              window.scrollTo({
                top: 0,
                left: 0,
                behavior: "smooth"
              });
              loader.classList.remove('loader--active');
            }, 1000)

          }
        })
      } else {
        loader.classList.remove('loader--active');
      }
    });
  }
}

export {checkTest}
