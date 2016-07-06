<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Illuminate\Support\Facades\Facade;
use Illuminate\Contracts\Container\Container;
use CupOfTea\WordPress\Foundation\AliasLoader;

class RegisterFacades
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        Facade::clearResolvedInstances();
        
        Facade::setFacadeApplication($app);
        
        AliasLoader::getInstance($app->make('config')->get('app.aliases'))->register();
    }
}
