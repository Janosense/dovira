const initTogglePhones = () => {
  const toggle = document.querySelector('.footer-calls__link--phones');
  const phones = '';
  if (toggle) {
    toggle.addEventListener('click', (evt) => {
      evt.preventDefault();

    });
  }
}


const toggleFooterCalls = () => {
  const footerCalls = document.querySelector('.footer-calls');
  const buttonUp = document.querySelector('.up-button');
  if (footerCalls) {
    initTogglePhones();
    const toggle = footerCalls.querySelector('.footer-calls__toggle');
    toggle.addEventListener('click', (evt) => {
      footerCalls.classList.toggle('footer-calls--active');

      if (buttonUp) {
        if (footerCalls.classList.contains('footer-calls--active')) {
          buttonUp.classList.remove('up-button--active');
        } else if (!footerCalls.classList.contains('footer-calls--active') && window.scrollY > 300) {
          setTimeout(() => {
            buttonUp.classList.add('up-button--active');
          }, 500)
        }
      }
    });
  }
}

export {toggleFooterCalls};
