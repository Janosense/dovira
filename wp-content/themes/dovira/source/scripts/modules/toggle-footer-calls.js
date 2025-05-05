
const toggleFooterCalls = () => {
  const footerCalls = document.querySelector('.footer-calls');
  if (footerCalls) {
    const toggle = footerCalls.querySelector('.footer-calls__toggle');
    toggle.addEventListener('click', (evt) => {
      footerCalls.classList.toggle('footer-calls--active');
    });
  }
}

export {toggleFooterCalls};
