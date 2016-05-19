<?php

namespace CupOfTea\WordPress\Theme;

use Illuminate\Container\Container;

abstract class Service
{
    public function __construct(Container $app)
    {
        $this->app = $app;
    }
}
