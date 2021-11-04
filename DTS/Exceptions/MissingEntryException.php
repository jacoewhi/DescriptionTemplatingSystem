<?php


namespace App\Console\Commands\DTS\Exceptions;


use Exception;
use Throwable;

/**
 * Class MissingEntryException
 *
 * thrown when the glossary tries to find a glossary entry for a term that does not exist
 *
 * @package App\Console\Commands\DTS\Exceptions
 */
class MissingEntryException extends Exception {

    /**
     * MissingEntryException constructor.
     * @param $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null) {

        if (empty($message)) {
            $message = "missing required entry";
        }

        parent::__construct($message, $code, $previous);
    }

}
