<?php

namespace CupOfTea\WordPress\Exception;

use Symfony\Component\Debug\Exception\FlattenException;

class UntracableFlattenException extends FlattenException
{
    public function toArray()
    {
        $exceptions = array();
        foreach (array_merge(array($this), $this->getAllPrevious()) as $exception) {
            $exceptions[] = array(
                'message' => $exception->getMessage(),
                'class' => $exception->getClass(),
                'trace' => [],
            );
        }
        
        return $exceptions;
    }
    
    public function setTraceFromException(\Exception $exception)
    {
        $this->trace = array();
    }
    
    public function setTrace($trace, $file, $line)
    {
        $this->trace = array();
    }
}
