# Design — Dovira

<!-- Adoption: documented from the code as-is (2026-09-08), no design files.
     The original Figma design is the client's; it is not in the repo. Shared
     design truth for all features: tokens, components, the screen list.
     Keep ≤80 lines; per-feature screens belong to FEATURE.md → UI. -->

## Design system source
- **Made in:** documented from code (Adoption); original: client Figma, not in repo
- **Source of truth in code:** `wp-content/themes/dovira/source/styles/` — `colors.css` (tokens), `fonts.css`, `components/*.css` (one file per component), `blocks/*.css` (one file per ACF block), all imported by `app.css`; admin/editor styles in `admin.css`
- **Strings:** front-end strings are either PHP literals in templates (uk) or Polylang strings registered in `inc/utils/polylang-string-translations.php`; a new user-visible string is registered there, never left as an untranslated literal.

## Tokens
Colors (`source/styles/colors.css`, CSS custom properties): `--color-white #fff`, `--color-black #1b1b1b`, `--color-gray-dark #3c3c3c`, `--color-gray #727270`, `--color-gray-light #f5f5f5`, `--color-violet-light #c06f94` (accent: active city pin, button hover), `--color-transparent`. The editor palette registers one extra color `color-cyan #1995AD` (legacy).
Typography: Inter (Google Fonts) for headings and buttons, weight 400; Oswald and Raleway loaded but secondary; Open Sans self-hosted (`fonts.css`) for body. Heading scale: h1 46.1px, h2 39.1px, h3 33.2px, h4 23.6px, h5 22px (`components/heading.css`); line-height 1.
Spacing / radii: buttons `padding 14px 22px`, `border-radius 5px`, min-width 152px; sections via `.section--mb-standard` / `--mb-none`; container `.wrapper` / `.wrapper--tight`.
Breakpoints (mobile-first `min-width`): 768, 1024, 1280, 1440, 1920; `@media (hover: hover)` for hover states.

## Components
| Component | Variants / states | In code |
|---|---|---|
| Button `.button` | `--black`, `--black-border`, `--white`; `:disabled`, hover | `components/button.css` |
| Heading `.heading--h1…h6`, Caption, Text | color/level/style set per block from ACF (`heading_color`, `heading_level`, `heading_style`) | `heading.css`, `caption.css`, `text.css` |
| Header + main nav + mobile nav | hamburger (`--elastic`), submenu toggle button (`.menu-item__toggle`), phones per city, search form (desktop/mobile) | `header.css`, `main-nav.css`, `mobile-nav.css`, `hamburger.css`, `search-form.css` |
| Location switcher | desktop / mobile; active city with pin icon | `location-switcher.css`, `site-header.php` |
| Language switcher | desktop / mobile; labels shortened to "Укр / Рус" | `language-switcher-mobile.css`, `pll_the_languages` filter |
| Footer, footer calls, up button, social links | — | `footer.css`, `footer-calls.css`, `up-button.css`, `social-links.css` |
| Section header (heading + caption + CTA) and background/gradient layer | every block shares `general` tab fields: `background_color`, `background_image`, `show_gradient_layer`, `gradient_tone`, `gradient_direction`, `margin_bottom` | `section.css`, `gradient-layer.css` |
| Service price list | city tabs (`.service__prices-toggles`), search + reset, per-row price or phone | `service.css`, `city-toggle.css`, `init-service-price-lists.js`, `services-search.js` |
| Breadcrumbs | — | `breadcrumbs.css` |
| Employee card / page | photo, position, quote, socials, facts, contact CTA | `employee.css`, `single-employee.php` |
| Vacancy card / page + application form (CF7) | open/closed, salary variants | `vacancy.css`, `application.css`, `contact-form7.css` |
| Questionnaire form | multi-field with iMask phone, REST submit, success/error states | `questionary-form.css`, `questionary.css`, `questionary-form-handler.js` |
| Entity status badge (admin) | `--new`, `--processed`, `--in_processing`, `--interview_scheduled`, `--rejected` | `admin.css` (excluded from `.acf-block-preview` prefixing) |
| Swiper carousel, Fancybox gallery, animations | — | `init-swiper.js`, `init-fancybox.js`, `init-animations.js` |
| ACF blocks (21) | `about`, `accordion`, `contacts`, `contacts-simple`, `custom-html`, `employees`, `entities-grid`, `entity-links`, `files`, `gallery`, `hero`, `links-group`, `news`, `numbers`, `questionary`, `rich-text`, `seo-text`, `services`, `text-form`, `text-image`, `vacancies` | `inc/acf/blocks/{name}/template.php` + `styles/blocks/{name}.css` |

## Screens
<!-- Names are the ones sprint steps use in Verification (manual). No design export exists; Design ref = the template file. -->
| Screen | Feature | Route / entry | Design ref | States covered |
|---|---|---|---|---|
| Home | core | `/` (front page, blocks) | `index.php` + blocks | uk / ru; Kharkiv / Kyiv |
| Page (block page) | core | any `page` | `index.php` + blocks | — |
| Service | core | `/services/{slug}/` | `single-service.php` | with prices per city, empty search, no price → phone |
| Sub-service | core | `/services/{parent}/{slug}/` | `templates/sub-service.php` | — |
| Team member | core | `/team/{slug}/` | `single-employee.php` | with / without contact CTA |
| Vacancy | core | `/vacancies/{slug}/` | `single-vacancy.php` | open (form) / closed |
| Application (admin view) | core | `/applications/{slug}/` | `single-application.php` | statuses |
| Questionnaire (admin view) | core | `/{slug}/` (questionary) | `single-questionary.php` | — |
| Post / search / 404 | core | `/{slug}/`, `?s=`, 404 | `single.php`, `search.php`, `404.php` | no results |
| Admin lists: Conversations, Applications, Questionaries | core | `wp-admin/edit.php?post_type=…` | `inc/utils/*.php` | filters, status badges |

## Flows
- **Find a price:** Home → Services block → Service → city tab → search.
- **Apply for a vacancy:** Vacancy (open) → CF7 form with CV → Telegram → Applications admin list → status changes.
- **Contact / franchise:** page with Text/Form block → CF7 → Conversations list → processed.
- **Blood donor:** page with Questionary block → REST save → Questionaries list.

## Out of v1 design
- Legacy LMS screens (`templates/sign-in.php`, `sign-up.php`, `profile.php`, `testing.php`, `learning-plan` styles) are not designed screens of this product; kept as-is pending the DECISIONS.md open question.
