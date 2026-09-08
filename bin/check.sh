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
#
# Needs PHP >= 8.3 and Composer on PATH (the DDEV web container also qualifies:
# ddev exec bash bin/check.sh).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="${ROOT}/wp-content/plugins/ga-telegram-bridge"
THEME="${ROOT}/wp-content/themes/dovira"

if ! php -r 'exit( PHP_VERSION_ID >= 80300 ? 0 : 1 );'; then
	echo "check: PHP $(php -r 'echo PHP_VERSION;') is too old — the dev tooling needs PHP 8.3 or newer." >&2
	exit 1
fi

if [ ! -d "${PLUGIN}/vendor" ]; then
	echo "==> composer install (ga-telegram-bridge)"
	( cd "${PLUGIN}" && composer install --no-interaction --no-progress )
fi

echo "==> PHPCS (ga-telegram-bridge)"
( cd "${PLUGIN}" && vendor/bin/phpcs )

echo "==> PHPStan (ga-telegram-bridge)"
( cd "${PLUGIN}" && vendor/bin/phpstan analyse --no-progress )

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

echo "==> check: all green"
