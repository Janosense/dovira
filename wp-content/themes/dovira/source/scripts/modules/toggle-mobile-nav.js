const toggleMobileNav = () => {
  const toggleButton = document.querySelector('.header__hamburger');
  const mobileNav = document.querySelector('.mobile-nav');

  if(toggleButton && mobileNav) {
    toggleButton.addEventListener('click', () => {
      mobileNav.classList.toggle('mobile-nav--active');
      toggleButton.classList.toggle('is-active');
    });
  }
}

export {toggleMobileNav}
