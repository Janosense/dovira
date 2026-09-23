// Feature search-stats: sends each search to the one route that records it
// (docs/features/search-stats/FEATURE.md). The text goes as typed; the server
// normalizes and validates it.
const ENDPOINT = '/wp-json/dovira/v1/search-stats/record';
// A filter's value is sent once it has rested this long, or when the field loses focus.
const PAUSE_MS = 1500;
// The two filters start filtering at three characters, and so does recording.
const MIN_LENGTH = 3;

// JSON.stringify drops undefined keys: a filter sends no `results` (the route
// refuses one), the site search no `context_id`. Nothing reads the answer, and
// a failure is silent — recording never disturbs the page.
const recordSearch = (level, query, contextId, results) => {
  const body = JSON.stringify({level, query, context_id: contextId, results});
  let sent = false;

  try {
    sent = navigator.sendBeacon(ENDPOINT, new Blob([body], {type: 'application/json'}));
  } catch (error) {
    sent = false;
  }

  if (!sent) {
    fetch(ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body,
      keepalive: true,
    }).catch(() => {});
  }
};

// The header search: search.php prints the query and how many results it lists
// on the results section; sent once per results page, never for an empty query.
const recordSiteSearch = () => {
  const section = document.querySelector('[data-search-stats-query]');

  if (section && section.dataset.searchStatsQuery.trim() !== '') {
    recordSearch('site', section.dataset.searchStatsQuery, undefined, Number(section.dataset.searchStatsResults));
  }
};

// A filter's search: sent after the pause or on blur, when it has at least
// MIN_LENGTH characters and differs from the last value this input sent in this
// page view. The trim is a gate only; the value goes as typed. Its own
// listeners leave the filter's handlers untouched.
const debouncedRecorder = (input, level, contextId) => {
  let timer = null;
  let lastSent = null;

  const send = () => {
    clearTimeout(timer);
    const value = input.value.trim();

    if (value.length >= MIN_LENGTH && value !== lastSent) {
      lastSent = value;
      recordSearch(level, input.value, contextId);
    }
  };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(send, PAUSE_MS);
  });
  input.addEventListener('blur', send);
};

export {recordSearch, recordSiteSearch, debouncedRecorder}
