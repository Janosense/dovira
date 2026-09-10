# dovira theme — code-area conventions

<!-- Code-area CLAUDE.md: ONE per code directory (an app, package, or
     service; in WP — a theme or plugin), even when
     several features live inside it. Delta only, ≤60 lines — everything
     shared (core rules, step protocol, git model) stays in the ROOT CLAUDE.md.
     Claude Code loads this file automatically when working inside this
     directory. Feature docs do NOT live here — they are in
     docs/features/{feature}/ (see the Features table in root CLAUDE.md). -->

The `dovira` classic theme — the whole custom codebase of the site: content
model, ACF blocks, templates, form pipelines, REST, CLI (feature `core`,
as-is) plus, from now on, feature modules under `inc/features/{name}/`.

## Feature isolation (if this area hosts several features)
- Each feature lives in `inc/features/{name}/` with its own `bootstrap.php`
  (its hooks, requires, block/field registrations); its blocks in
  `inc/features/{name}/blocks/{block}/` (same `block.json` + `fields.php` +
  `template.php` trio), its styles/scripts under `source/{styles,scripts}/features/{name}/`.
- `functions.php` contains exactly one registration line per feature —
  `require_once TEMPLATE_DIR . '/inc/features/{name}/bootstrap.php';` — nothing else feature-specific.
- Shared code (`inc/*.php`, `inc/acf/**`, `inc/utils/**`, `inc/rest-api/**`,
  `inc/cli/**`, `template-parts/**`, `header.php`/`footer.php`,
  `source/styles/{colors,app,admin}.css`, `source/scripts/app.js`) is changed
  ONLY as an explicit plan task marked **"touches shared code — may affect other
  features"**; the plan must list which features consume it.
- A feature never writes to data owned by another feature (see each FEATURE.md → Data).

## Area conventions
- PHP: procedural, `dovira_` prefix for functions, `dovira\` namespace for
  classes (`inc/rest-api`, `inc/cli`); WordPress coding style (tabs, spaces
  inside parentheses, Yoda-free). Text domain `dovira`.
- Post types / taxonomies: one file each returning `['post_type'|'taxonomy', 'args']`,
  glob-loaded from `inc/post-types/` and `inc/taxonomies/`.
- ACF: field groups via `StoutLogic\AcfBuilder\FieldsBuilder` on `acf/init`;
  a block = directory under `inc/acf/blocks/` (or the feature's `blocks/`)
  registered by scanning — then add its name to `dovira_allowed_block_types()`.
  Read fields through `dovira_get_acf_field()` (null-safe if ACF is off).
- Templates: classic PHP; escape on output; strings uk literal or
  `pll__()` for anything registered in `inc/utils/polylang-string-translations.php`.
- Front-end: `source/` is the source, `assets/` the committed build. Modules
  are ES modules imported dynamically from `source/scripts/app.js`; styles are
  one CSS file per component/block imported by `source/styles/app.css`,
  PostCSS preset-env (nesting allowed), tokens from `colors.css`. Admin/editor
  CSS goes to `admin.css` (auto-prefixed `.acf-block-preview`).
- Env: theme `.env` (phpdotenv) — `WP_ENVIRONMENT_TYPE`, `VITE_SERVER_PORT`,
  `VITE_ENTRY_POINT`, `ANTHROPIC_API_KEY`; never read secrets any other way.
- REST: controllers extend `WP_REST_Controller`, namespace `dovira/v1`,
  registered in `inc/rest-api.php`; every route sets an explicit `permission_callback`.
- CLI: classes in `inc/cli/`, registered in `inc/cli.php` under `wp dovira …`, guarded by `WP_CLI`.
- Never commit `.env`, `node_modules/`, `composer.lock`/`package-lock.json` (theme `.gitignore`).

## Local commands
```bash
cd wp-content/themes/dovira && npm start      # Vite dev server (HMR, WP_ENVIRONMENT_TYPE=development)
cd wp-content/themes/dovira && npm run build  # rebuild assets/ before committing front-end changes
ddev wp dovira translate --help               # CLI command reference
```
