<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Dotenv\Dotenv;

use Illuminate\Contracts\Container\Container;

class Environment
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        $app->singleton('env', function ($app) {
            return new Dotenv($app->basePath());
        });
        
        $env = $app->make('env');
        
        $env->overload();
        $env->required([
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'DB_HOST'
        ]);
    }
}
