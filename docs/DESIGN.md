# Design — Dovira

<!-- playbook: v3.0.4. State document: pointers and an index, no values.
     Read whole before UI work, then the feature's FEATURE.md → UI. Shape,
     triggers and what does not belong here: docs/CONTRACTS.md →
     `### docs/DESIGN.md`. -->

## Source
- The design system: none in Claude Design and no copy in `docs/design-system/`; the original design is the client's Figma, outside the repository, and the code in `wp-content/themes/dovira/source/styles/` is the design's source of truth.
- Feature designs: none; a screen is its template file (the feature's `FEATURE.md` → UI names it), and `city-popup`'s dialog is fixed as a written template in its `FEATURE.md`.
- Files left out because they are not text: none — the fonts are Google Fonts and the self-hosted Open Sans in `source/fonts/`.
- Artboards: none, so a screen is found by its route and template in `FEATURE.md` → UI.
- Strings: every visible string is a Polylang string registered in `inc/utils/polylang-string-translations.php` and printed with `dovira_translate_string()`; a JS module reads its strings from `data-*` attributes of its element.

## Tokens
- Tokens live in `wp-content/themes/dovira/source/styles/colors.css` (colours as CSS custom properties), `fonts.css` (faces) and `components/heading.css` (the heading scale); breakpoints are written in each file as mobile-first `min-width` queries at 768, 1024, 1280, 1440 and 1920 px.
- A colour token `--color-{name}` is used as `var(--color-{name})`; a heading level is the class `.heading--h{n}`; the block editor's palette registers the same names for blocks' colour fields.

## Components
| Component | In code | Artboard |
|---|---|---|
| Button | `.button` (`--black`, `--black-border`, `--white`), `source/styles/components/button.css` | — |
| Heading, caption, text | `.heading--h1…h6`, `caption.css`, `text.css` | — |
| Header with main and mobile navigation, phones and search form | `template-parts/header/site-header.php`; `header.css`, `main-nav.css`, `mobile-nav.css`, `hamburger.css`, `search-form.css` | — |
| City switcher | `site-header.php` (`data-city` on the link to the other city); `location-switcher.css` | — |
| Language switcher | `pll_language_switcher_output` filter in `inc/template-functions.php`; `language-switcher-mobile.css` | — |
| Footer, footer calls, up button, social links | `footer.php`, `template-parts/footer/*`; `footer.css`, `footer-calls.css`, `up-button.css`, `social-links.css` | — |
| Section with the shared `general` fields (background, gradient, bottom margin) | every `acf/*` block; `section.css`, `gradient-layer.css` | — |
| Service price list with city tabs and search | `single-service.php`; `service.css`, `city-toggle.css`, `init-service-price-lists.js`, `services-search.js` | — |
| Breadcrumbs | `breadcrumbs.css` | — |
| Employee card and page | `single-employee.php`; `employee.css` | — |
| Vacancy card and page with the application form | `single-vacancy.php`; `vacancy.css`, `application.css`, `contact-form7.css` | — |
| Blood-donor form | block `questionary`; `questionary-form.css`, `questionary.css`, `questionary-form-handler.js` | — |
| Record status badge (admin) | `.entity-status--{status}` in `admin.css` | — |
| Carousel, gallery, animations | `init-swiper.js`, `init-fancybox.js`, `init-animations.js` | — |
| City question dialog | `inc/features/city-popup/Dialog.php`; `source/styles/features/city-popup/city-popup.css`; `source/scripts/features/city-popup/city-popup.js` | — (template in `docs/features/city-popup/FEATURE.md` → UI) |
| ACF blocks (21): `about`, `accordion`, `contacts`, `contacts-simple`, `custom-html`, `employees`, `entities-grid`, `entity-links`, `files`, `gallery`, `hero`, `links-group`, `news`, `numbers`, `questionary`, `rich-text`, `seo-text`, `services`, `text-form`, `text-image`, `vacancies` | `inc/acf/blocks/{name}/template.php` + `source/styles/blocks/{name}.css` | — |
