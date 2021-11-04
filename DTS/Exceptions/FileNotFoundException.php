<?php


namespace App\Console\Commands\DTS\Exceptions;


use Exception;
use Throwable;

/**
 * Class FileNotFoundException
 *
 * thrown when the program is unable to access any of the read or write files needed for
 * the DTS to properly execute
 *
 * @package App\Console\Commands\DTS\Exceptions
 */
class FileNotFoundException extends Exception {

    /**
     * FileNotFoundException constructor.
     * @param $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null) {

        if (empty($message)) {
            $message = "file not found";
        }

        parent::__construct($message, $code, $previous);
    }
}
