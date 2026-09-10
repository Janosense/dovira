# Verification — ga-telegram-bridge, Sprint 1, Step 1

**Delta-audit, plugin skeleton, check command** · written at close on 2026-09-08
Branch merged: `ga-telegram-bridge/sprint-1-report-on-demand` → `master`

What this step promises: the project has a working check command, and the plugin
installs and activates on the local site without doing anything yet — no menu,
no settings, no network calls. Those arrive in Steps 3–8.

Run everything from the repo root (`/Users/tymofii/Projects/php/dovira`).

## 1. The gate is green
```bash
bash bin/check.sh; echo "exit=$?"
```
Expected: four sections in this order — `==> PHPCS`, `==> PHPStan` (`[OK] No errors`),
`==> PHPUnit` (`OK (3 tests, 4 assertions)`), `==> php -l (theme dovira)` with
`108 files checked` — then `==> check: all green` and `exit=0`.

It also works inside the container (PHP 8.3, the version production-like code runs on):
```bash
ddev exec bash bin/check.sh; echo "exit=$?"
```

## 2. Negative check — the gate must fail, and fail early
Introduce a deliberate coding-standards violation:
```bash
printf '<?php\n$x=1;\n' > wp-content/plugins/ga-telegram-bridge/src/TempViolation.php
bash bin/check.sh; echo "exit=$?"
```
Expected: PHPCS reports errors in `src/TempViolation.php` (missing file docblock,
missing spaces around `=`, …), the run **stops there** — `==> PHPStan` and
`==> PHPUnit` never print — and `exit=2` (any non-zero counts).

Clean up and confirm the gate returns to green:
```bash
rm wp-content/plugins/ga-telegram-bridge/src/TempViolation.php
bash bin/check.sh; echo "exit=$?"   # exit=0 again
```

## 3. The plugin activates with no notices and no menu
```bash
ddev wp plugin list --name=ga-telegram-bridge --fields=name,status,version
ddev wp plugin activate ga-telegram-bridge
```
Expected: the plugin is listed at version `0.1.0`, and activation prints
`Plugin 'ga-telegram-bridge' activated.` with **no** PHP warnings or notices.

Then open https://dovira.ddev.site/wp-admin/plugins.php — "Google Analytics →
Telegram bridge" is Active, with no error banner under it.

**Negative check:** open https://dovira.ddev.site/wp-admin/options-general.php —
the Settings menu has **no** "GA → Telegram" item, and there is no new top-level
menu anywhere. The admin page is Step 3; if a menu appears now, something outside
this step's scope was added.

## 4. The plugin's one hook is registered
```bash
ddev wp eval 'var_dump( has_action( "init", array( "GaTelegramBridge\\Plugin", "load_textdomain" ) ) );'
```
Expected: `int(10)` — `Plugin::boot()` ran and registered the translation loader
at the default priority. (`bool(false)` would mean the autoloader or `boot()` did
not run.)

## 5. The OpenSSL activation guard
The extension is compiled into the PHP binary both on the host and in DDEV
(`ddev exec php -n -m | grep openssl` still prints `openssl`), so a real
activation without OpenSSL cannot be produced here. Check the guard's decision
directly instead:
```bash
ddev wp eval 'echo GaTelegramBridge\Plugin::activation_blocked_message( false ), PHP_EOL;'
ddev wp eval 'var_dump( GaTelegramBridge\Plugin::activation_blocked_message( true ) );'
```
Expected: the first prints
`Google Analytics → Telegram bridge needs the PHP OpenSSL extension to sign Google API requests. Ask your host to enable it, then activate the plugin again.`
the second prints `NULL` (nothing blocks activation when OpenSSL is present).
The same two cases are asserted by `tests/Unit/PluginTest.php`, which runs in step 1.

## 6. Nothing that should not be in git is in git
```bash
git ls-files wp-content/plugins/ga-telegram-bridge | grep -c '^wp-content/plugins/ga-telegram-bridge/vendor/'   # 0
git ls-files wp-content/plugins/ga-telegram-bridge
```
Expected: `0` vendor files, and the tracked list is exactly: `CLAUDE.md`,
`.gitignore`, `composer.json`, `composer.lock`, `ga-telegram-bridge.php`,
`languages/.gitkeep`, `phpcs.xml.dist`, `phpstan.neon.dist`, `phpunit.xml.dist`,
`readme.txt`, `src/Plugin.php`, `tests/…` (3 files). No key, token or chat id
appears anywhere in them — the plugin has no settings yet.

## 7. Leave the site as you found it (optional)
```bash
ddev wp plugin deactivate ga-telegram-bridge
```
Deactivation is clean: the plugin has no options, no cron events and no database
rows in this step (`uninstall.php` arrives in Sprint 2).
