<?php


namespace App\Console\Commands\DTS\Exceptions;


use Exception;
use Throwable;

/**
 * Class CouldNotLogErrorException
 *
 * designed to be thrown when logging a failed description generation fails
 * this allows us to ensure that the program stops if an error occurs and is not reported properly
 *
 * @package App\Console\Commands\DTS\Exceptions
 */
class CouldNotLogErrorException extends Exception {

    /**
     * BadSentenceTypeException constructor.
     * @param $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null) {

        if (empty($message)) {
            $message = "logging error to output file failed";
        }

        parent::__construct($message, $code, $previous);
    }

}
