<?php
/**
 * The failure of a Telegram Bot API call, in words an administrator can act on.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use RuntimeException;

/**
 * Thrown by TelegramClient. Its message is shown in wp-admin and written to the
 * run log, so it never carries the bot token — which travels in the request URL
 * and would otherwise reach a message through a transport error.
 */
final class TelegramException extends RuntimeException {
}
