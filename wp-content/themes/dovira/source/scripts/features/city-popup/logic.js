// Feature city-popup: every decision the city question makes, with no DOM
// access, so Vitest covers it (docs/features/city-popup/FEATURE.md). The DOM
// glue in city-popup.js only reads the page and calls these.
const CITY_COOKIE = 'dovira_city';
const DISMISS_COOKIE = 'dovira_city_dismissed';
const CITIES = ['kharkiv', 'kyiv'];
// Added to every move to the other install; its 404 under the service base then
// falls back to the services list (DECISIONS "A service missing on the chosen
// install falls back to its services list through a marker in the URL").
const MARKER = 'city-popup';
const DAY_SECONDS = 86400;

const withSlash = (path) => (path.endsWith('/') ? path : `${path}/`);

const toUrl = (href, base) => {
  try {
    return new URL(href, base);
  } catch (error) {
    return null;
  }
};

// A link is a target when it stays on the current host and its path starts
// with the path of one of the target URLs (the services list, the contacts
// page, the service base). "/contacts" counts like "/contacts/", where
// WordPress sends it.
const isTarget = (href, currentOrigin, targets) => {
  const url = toUrl(href, `${currentOrigin}/`);

  if (!url || url.origin !== currentOrigin) {
    return false;
  }

  const path = withSlash(url.pathname);

  return targets.some((target) => {
    const targetUrl = toUrl(target);

    return Boolean(targetUrl) && targetUrl.origin === currentOrigin && path.startsWith(withSlash(targetUrl.pathname));
  });
};

// A click the browser keeps for itself: a modifier key, a button other than the
// main one, or a link that opens another window.
const isPlainClick = (click, linkTarget) => click.button === 0
  && !click.ctrlKey && !click.metaKey && !click.shiftKey && !click.altKey
  && (!linkTarget || linkTarget === '_self');

// The same path, query and fragment on the other install, with the marker added
// once. The query is appended to as written: URLSearchParams would re-encode it.
const crossUrl = (href, origin) => {
  const url = new URL(href);
  let search = url.search.length > 1 ? url.search : '';

  if (url.searchParams.get(MARKER) !== '1') {
    search = `${search ? `${search}&` : '?'}${MARKER}=1`;
  }

  return `${origin}${url.pathname}${search}${url.hash}`;
};

const cityFromAttr = (value) => (CITIES.includes(value) ? value : null);

// What a click on a target does: a saved city wins over a dismissal, and an
// unknown saved value counts as none.
const decide = (cookies, currentCity) => {
  const saved = cityFromAttr(cookies[CITY_COOKIE]);

  if (saved) {
    return saved === currentCity ? 'go-current' : 'go-city';
  }

  return cookies[DISMISS_COOKIE] === '1' ? 'go-current' : 'ask';
};

const cookieTail = (domain, secure) => `; Domain=${domain}; Path=/; SameSite=Lax${secure ? '; Secure' : ''}`;

const cityCookie = (city, days, domain, secure) => `${CITY_COOKIE}=${city}; Max-Age=${days * DAY_SECONDS}${cookieTail(domain, secure)}`;

// What a click on a link with data-city writes (the header switcher): the city
// cookie for a known city, nothing for any other value.
const namedCityCookie = (value, days, domain, secure) => {
  const city = cityFromAttr(value);

  return city ? cityCookie(city, days, domain, secure) : null;
};

// A session cookie: no Max-Age, so it ends when the browser is closed.
const dismissCookie = (domain, secure) => `${DISMISS_COOKIE}=1${cookieTail(domain, secure)}`;

const readCookies = (string) => string.split(';').reduce((cookies, pair) => {
  const at = pair.indexOf('=');

  if (at > 0) {
    cookies[pair.slice(0, at).trim()] = pair.slice(at + 1).trim();
  }

  return cookies;
}, {});

// A click on the backdrop and one on the dialog's own padding both have the
// <dialog> as their target; only the point tells them apart.
const isOutside = (rect, x, y) => x < rect.left || x > rect.right || y < rect.top || y > rect.bottom;

export {
  CITY_COOKIE,
  DISMISS_COOKIE,
  isTarget,
  isPlainClick,
  crossUrl,
  decide,
  cityCookie,
  namedCityCookie,
  dismissCookie,
  readCookies,
  cityFromAttr,
  isOutside,
};
