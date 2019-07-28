<?php

namespace QUI\Lockclient\Exceptions;

use Throwable;

class LockServerException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        /*
         *  As the lock client must be usable as standalone package for the setup, we must include a failsafe check
         *  to avoid using classes that are not present during the setup.
         *  This will check if the QUI and the QUI\Exception classes exist before throwing them
         *  and defaulting to a standard exception if they are not defined
         */
        if (class_exists('QUI') && class_exists('QUI\Exception')) {
            throw new QUI\Exception($message, $code, $this);
        }

        // The QUI\Exception gets called with an array as message, which gets used to get a translated error message.
        // The array contains the package in the first field and the locale variable name in the second.
        if (is_array($message) && isset($message[1])) {
            $message = $message[1];
        }
        parent::__construct($message, $code, $previous);
    }
}
