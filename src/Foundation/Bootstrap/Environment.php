<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Dotenv\Dotenv;
use Illuminate\Contracts\Container\Container;

class Environment
{
    protected $detectFromServerVars = [
        'HTTP_HOST',
        'SERVER_NAME',
    ];
    
    protected $app;
    
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        $this->app = $app;
        
        $this->app->singleton('env', function ($app) {
            return new Dotenv(base_path(), $this->getEnvFileName());
        });
        
        $env = $this->app->make('env');
        
        $env->overload();
        $env->required([
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'DB_HOST',
        ]);
    }
    
    protected function getEnvFileName()
    {
        if (file_exists(base_path('env.php')) && ($filename = include base_path('env.php')) !== 1) {
            return $filename;
        }
        
        foreach ($this->detectFromServerVars as $var) {
            if (file_exists(base_path('.env.' . $_SERVER[$var]))) {
                return '.env.' . $_SERVER[$var];
            }
        }
        
        if (file_exists(base_path('.env.' . basename(base_path())))) {
            return '.env.' . basename(base_path());
        }
        
        return '.env';
    }
}
