<?php

namespace CupOfTea\WordPress\View;

use BadMethodCallException;
use CupOfTea\Counter\Counter;

class Loop
{
    protected $loop;
    
    protected $depth;
    
    protected $parent;
    
    public function __construct(Counter $loop, Loop $parent = null, $depth = 1)
    {
        $this->loop = $loop;
        $this->depth = $depth;
        $this->parent = $parent;
    }
    
    public function parent()
    {
        return $this->parent;
    }
    
    public function depth()
    {
        return $this->depth;
    }
    
    public function __call($method, $args)
    {
        if (method_exists($this->loop, $method)) {
            return call_user_func_array([$this->loop, $method], $args);
        }
        
        throw new BadMethodCallException('The method Loop::' . $method . ' does not exist.');
    }
}
