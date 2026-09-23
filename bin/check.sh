#!/usr/bin/env bash
#
# Project check command — the gate every commit passes.
# See docs/TECH-STACK.md → Check command.
#
# Runs, exiting non-zero on the first failure:
#   1. PHPCS   (WordPress Coding Standards)  in wp-content/plugins/ga-telegram-bridge
#   2. PHPStan (level 8)                     in wp-content/plugins/ga-telegram-bridge
#   3. PHPUnit (unit tests)                  in wp-content/plugins/ga-telegram-bridge
#   4. php -l  over the dovira theme's PHP files
#   5. PHPUnit (unit tests)                  in wp-content/themes/dovira/tests
#   6. Vitest  (unit tests)                  in wp-content/themes/dovira
#
# Needs PHP >= 8.3, Composer, Node and npm on PATH. Stage 6 runs where the
# theme's node_modules/ was installed: Vite's native packages exist for one OS
# only, so the DDEV web container cannot use a node_modules/ installed on the
# host (docs/TECH-STACK.md → Check command).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="${ROOT}/wp-content/plugins/ga-telegram-bridge"
THEME="${ROOT}/wp-content/themes/dovira"
# The theme's test tooling is its own Composer project: the theme's vendor/ is
# committed runtime code and never gets dev packages (docs/DECISIONS.md, 2026-09-23).
THEME_TESTS="${THEME}/tests"

if ! php -r 'exit( PHP_VERSION_ID >= 80300 ? 0 : 1 );'; then
	echo "check: PHP $(php -r 'echo PHP_VERSION;') is too old — the dev tooling needs PHP 8.3 or newer." >&2
	exit 1
fi

if ! command -v node > /dev/null 2>&1 || ! command -v npm > /dev/null 2>&1; then
	echo "check: node and npm are required (the theme's JS tests, stage 6) — install Node.js, which brings npm, and put both on PATH." >&2
	exit 1
fi

if [ ! -d "${PLUGIN}/vendor" ]; then
	echo "==> composer install (ga-telegram-bridge)"
	( cd "${PLUGIN}" && composer install --no-interaction --no-progress )
fi

if [ ! -d "${THEME_TESTS}/vendor" ]; then
	echo "==> composer install (theme dovira tests)"
	( cd "${THEME_TESTS}" && composer install --no-interaction --no-progress )
fi

if [ ! -d "${THEME}/node_modules" ]; then
	echo "==> npm install (theme dovira)"
	( cd "${THEME}" && npm install --no-audit --no-fund )
fi

# Vite (which Vitest runs on) loads a native Rollup package built for one OS.
# A node_modules/ installed on another OS — the host's, seen from the DDEV
# container — fails here, before any stage, instead of at stage 6 with Rollup's
# advice to delete node_modules/, which would break the build on the host.
if ! ( cd "${THEME}" && node --input-type=module -e "await import('vite')" ) > /dev/null 2>&1; then
	echo "check: Vite does not load here ($(uname -sm)) — the theme's node_modules/ was installed on another OS, or is incomplete." >&2
	echo "       Run the gate where node_modules/ was installed (the host), or run npm install in the theme on this system." >&2
	echo "       Do not delete node_modules/ from the DDEV container: the host's npm start / npm run build need it." >&2
	exit 1
fi

echo "==> PHPCS (ga-telegram-bridge)"
( cd "${PLUGIN}" && vendor/bin/phpcs )

echo "==> PHPStan (ga-telegram-bridge)"
# --memory-limit: PHPStan's own default is the PHP CLI's 512M, and the
# WordPress stubs each worker loads need more than that on their own — the
# analysis peaks at ~730M single-threaded whether or not tests/ is included.
( cd "${PLUGIN}" && vendor/bin/phpstan analyse --no-progress --memory-limit=1G )

echo "==> PHPUnit (ga-telegram-bridge)"
( cd "${PLUGIN}" && vendor/bin/phpunit )

echo "==> php -l (theme dovira)"
count=0
while IFS= read -r -d '' file; do
	if ! php -l "$file" > /dev/null 2>&1; then
		echo "Syntax error:" >&2
		php -l "$file" >&2 || true
		exit 1
	fi
	count=$(( count + 1 ))
done < <(find "${THEME}" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)
echo "    ${count} files checked"

echo "==> PHPUnit (theme dovira)"
( cd "${THEME_TESTS}" && vendor/bin/phpunit )

echo "==> Vitest (theme dovira)"
( cd "${THEME}" && npm test )

echo "==> check: all green"
