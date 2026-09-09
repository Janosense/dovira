<?php
/**
 * The failure of a Google Analytics Data API call, in words an administrator
 * can act on.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use RuntimeException;

/**
 * Thrown by GaClient. Its message is shown in wp-admin and written to the run
 * log, so it never carries the key, the JWT or the access token.
 */
final class GaClientException extends RuntimeException {
}
