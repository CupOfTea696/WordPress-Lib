<?php

namespace CupOfTea\WordPress\Exception;

use Symfony\Component\Debug\Exception\FlattenException;

class UntracableFlattenException extends FlattenException
{
    public function toArray()
    {
        $exceptions = [];
        foreach (array_merge([$this], $this->getAllPrevious()) as $exception) {
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'class' => $exception->getClass(),
                'trace' => [],
            ];
        }
        
        return $exceptions;
    }
    
    public function setTraceFromException(\Exception $exception)
    {
        $this->trace = [];
    }
    
    public function setTrace($trace, $file, $line)
    {
        $this->trace = [];
    }
}
