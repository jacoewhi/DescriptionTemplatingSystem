<?php


namespace App\Console\Commands\DTS\Exceptions;


use Exception;
use Throwable;

/**
 * Class BadSentenceTypeException
 *
 * designed to be thrown when the sentence type of one of the sentence templates passed
 * to an addTemplate method does not match the expected sentence type
 *
 * @package App\Console\Commands\DTS\Exceptions
 */
class BadSentenceTypeException extends Exception {

    /**
     * BadSentenceTypeException constructor.
     * @param $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null) {

        if (empty($message)) {
            $message = "sentence type does not match expected";
        }

        parent::__construct($message, $code, $previous);
    }

}
