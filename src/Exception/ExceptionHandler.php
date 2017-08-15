<?php

namespace CupOfTea\WordPress\Exception;

use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;

class ExceptionHandler extends SymfonyExceptionHandler
{
    public function sendPhpResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = UntracableFlattenException::create($exception);
        }
        
        parent::sendPhpResponse($exception);
    }
    
    /**
     * Gets the full HTML content associated with the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The HTML content as a string
     */
    public function getHtml($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = UntracableFlattenException::create($exception);
        }
        
        return parent::getHtml($exception);
    }
}
