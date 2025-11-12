(async () => {
  const {initAnimations} = await import('@scripts/modules/init-animations');
  const {initFancybox} = await import('@scripts/modules/init-fancybox');
  const {toggleAccordion} = await import('@scripts/modules/toggle-accordion');
  const {toggleMobileNav} = await import('@scripts/modules/toggle-mobile-nav');
  const {servicesSearch} = await import('@scripts/modules/services-search');
  const {initServicePriceLists} = await import('@scripts/modules/init-service-price-lists');
  const {initSwiper} = await import('@scripts/modules/init-swiper');
  const {toggleCities} = await import('@scripts/modules/toggle-cities.js');
  const {toggleFooterCalls} = await import('@scripts/modules/toggle-footer-calls');
  const {questionaryFormHandler} = await import('@scripts/modules/questionary-form-handler.js');
  const {initImask} = await import('@scripts/modules/init-imask.js');

  initAnimations();
  initFancybox();
  toggleAccordion();
  toggleMobileNav();
  servicesSearch();
  initServicePriceLists();
  initSwiper()
  toggleCities();
  toggleFooterCalls();
  questionaryFormHandler();
  initImask()
})();
