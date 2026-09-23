// Feature search-stats: sends each search to the one route that records it
// (docs/features/search-stats/FEATURE.md). The text goes as typed; the server
// normalizes and validates it.
const ENDPOINT = '/wp-json/dovira/v1/search-stats/record';

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

export {recordSearch, recordSiteSearch}
