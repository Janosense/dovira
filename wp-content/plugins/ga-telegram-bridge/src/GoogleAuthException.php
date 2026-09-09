<?php
/**
 * The failure of signing in to Google, in words an administrator can act on.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use RuntimeException;

/**
 * Thrown by GoogleAuth. Its message is shown in wp-admin and written to the run
 * log, so it never carries the key, the JWT or the access token.
 */
final class GoogleAuthException extends RuntimeException {
}
