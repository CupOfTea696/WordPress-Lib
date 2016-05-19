<?php

namespace CupOfTea\WordPress;

use Illuminate\Container\Container;

abstract class Service
{
    public function __construct(Container $app)
    {
        $this->app = $app;
        
        $this->boot();
    }
    
    public function boot()
    {
        //
    }
}
