// Feature city-popup: the DOM glue around logic.js (docs/features/city-popup/FEATURE.md).
// Everything it knows comes from the markup PHP prints: the cookie settings on
// every page, and the dialog with the city, both origins and the targets on a
// blog page. It keeps no logic of its own; logic.js decides.
import {
  CITY_COOKIE,
  isTarget,
  isPlainClick,
  crossUrl,
  decide,
  cityCookie,
  dismissCookie,
  readCookies,
  cityFromAttr,
  isOutside,
} from '@scripts/features/city-popup/logic';

const initCityPopup = () => {
  const config = document.querySelector('[data-city-popup-config]');

  if (!config) {
    return;
  }

  const domain = config.dataset.cookieDomain;
  const days = Number(config.dataset.days);
  const secure = window.location.protocol === 'https:';
  const dialog = document.querySelector('dialog[data-city-popup]');
  const currentCity = dialog ? dialog.dataset.currentCity : null;
  const origins = dialog ? {kharkiv: dialog.dataset.kharkivOrigin, kyiv: dialog.dataset.kyivOrigin} : {};
  const targets = dialog ? Object.values(JSON.parse(dialog.dataset.targets || '{}')) : [];
  // The link the question was opened for.
  let pending = null;

  // Closed first, so Back (bfcache) does not bring the page back with the question open.
  const go = (href) => {
    dialog.close();
    window.location.assign(href);
  };

  const goToCity = (city, href) => (city === currentCity ? href : crossUrl(href, origins[city]));

  document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

    if (!link || event.defaultPrevented || !isPlainClick(event, link.target)) {
      return;
    }

    // A link that names a city (the header switcher) saves it, on every page;
    // the browser then follows the link.
    const named = cityFromAttr(link.dataset.city);

    if (named) {
      document.cookie = cityCookie(named, days, domain, secure);

      return;
    }

    if (!dialog || !isTarget(link.href, origins[currentCity], targets)) {
      return;
    }

    const cookies = readCookies(document.cookie);
    const action = decide(cookies, currentCity);

    if (action === 'go-current') {
      return;
    }

    event.preventDefault();

    if (action === 'go-city') {
      window.location.assign(goToCity(cookies[CITY_COOKIE], link.href));

      return;
    }

    pending = link.href;
    dialog.showModal();
  });

  if (!dialog) {
    return;
  }

  // ×, Esc and a click on the backdrop: stay on this site until the browser is closed.
  const dismiss = () => {
    document.cookie = dismissCookie(domain, secure);
    go(pending);
  };

  dialog.querySelectorAll('[data-city-popup-choice]').forEach((button) => {
    button.addEventListener('click', () => {
      const city = cityFromAttr(button.dataset.cityPopupChoice);

      if (city) {
        document.cookie = cityCookie(city, days, domain, secure);
        go(goToCity(city, pending));
      }
    });
  });

  dialog.querySelector('[data-city-popup-close]')?.addEventListener('click', dismiss);

  dialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    dismiss();
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog && isOutside(dialog.getBoundingClientRect(), event.clientX, event.clientY)) {
      dismiss();
    }
  });
};

export {initCityPopup};
