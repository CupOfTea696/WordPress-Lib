<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Illuminate\Contracts\Container\Container;

class BootApplication
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        $app->boot();
    }
}
