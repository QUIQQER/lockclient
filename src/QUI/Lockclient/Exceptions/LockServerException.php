<?php

namespace QUI\Lockclient\Exceptions;

/*
 *  As the lock client must be usable as standalone package for the setup, we must include a failsafe check
 *  to avoid using classes that are not present during the setup.
 *  This will check if the QUI and the QUI\Exception classes exist before extending them
 */

use Throwable;

if (class_exists('QUI') && class_exists('QUI\Exception')) {
    class LockServerException extends QUI\Exception
    {
    }
} else {
    class LockServerException extends \Exception
    {
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {
            // The QUI\Exception gets called with an array as message, which gets used to get a translated error message.
            // The array contains the package in the first field and the locale variable name in the second.
            if (is_array($message) && isset($message[1])) {
                $message = $message[1];
            }
            parent::__construct($message, $code, $previous);
        }
    }
}
