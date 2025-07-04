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
  if (footerCalls) {
    initTogglePhones();
    const toggle = footerCalls.querySelector('.footer-calls__toggle');
    toggle.addEventListener('click', (evt) => {
      footerCalls.classList.toggle('footer-calls--active');
    });
  }
}

export {toggleFooterCalls};
