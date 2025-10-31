(async () => {
  const {initAnimations} = await import('@scripts/modules/init-animations');
  const {initFancybox} = await import('@scripts/modules/init-fancybox');
  const {toggleAccordion} = await import('@scripts/modules/toggle-accordion');
  const {checkTest} = await import('@scripts/modules/check-test');
  const {authentication} = await import('@scripts/modules/authentication');
  const {resetProgress} = await import('@scripts/modules/reset-progress');
  const {toggleMobileNav} = await import('@scripts/modules/toggle-mobile-nav');
  const {servicesSearch} = await import('@scripts/modules/services-search');
  const {initServicePriceLists} = await import('@scripts/modules/init-service-price-lists');
  const {initSwiper} = await import('@scripts/modules/init-swiper');
  const {toggleEmployeesCity} = await import('@scripts/modules/toggle-employees-city');
  const {toggleFooterCalls} = await import('@scripts/modules/toggle-footer-calls');
  const {questionaryFormHandler} = await import('@scripts/modules/questionary-form-handler.js');

  initAnimations();
  initFancybox();
  toggleAccordion();
  checkTest();
  authentication();
  resetProgress();
  toggleMobileNav();
  servicesSearch();
  initServicePriceLists();
  initSwiper()
  toggleEmployeesCity();
  toggleFooterCalls();
  questionaryFormHandler();
})();
