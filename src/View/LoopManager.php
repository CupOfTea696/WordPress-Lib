<?php

namespace CupOfTea\WordPress\View;

use BadMethodCallException;
use Illuminate\Support\Arr;
use CupOfTea\Counter\Counter;

class LoopManager
{
    protected $loops = [];
    
    public function addLoop($loop)
    {
        $parent = Arr::last($this->loops);
        
        $this->loops[] = new Loop($loop, $parent, count($this->loops) + 1);
    }
    
    public function popLoop()
    {
        array_pop($this->loops);
    }
    
    public function getLastLoop()
    {
        if ($last = Arr::last($this->loops)) {
            return (object) $last;
        }
    }
}
