<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Illuminate\Contracts\Container\Container;

class RegisterServices
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        foreach (config('app.services') as $key => $services) {
            foreach ((array) $services as $service) {
                $app->alias($key, $service);
            }
        }
    }
}
