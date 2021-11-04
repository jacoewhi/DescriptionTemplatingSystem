<?php


namespace App\Console\Commands\DTS\Exceptions;


use Exception;
use Throwable;

/**
 * Class MissingTemplateException
 *
 * thrown when trying to get a template for a specification when there aren't any unused templates left
 * if the exceptions code is 1 then this indicates that it ran out of primary templates
 *
 * @package App\Console\Commands\DTS\Exceptions
 */
class MissingTemplateException extends Exception {

    /**
     * MissingTemplateException constructor.
     * @param $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message, $code = 0, Throwable $previous = null) {

        if (empty($message)) {
            $message = "missing required template";
        }

        parent::__construct($message, $code, $previous);
    }

}
