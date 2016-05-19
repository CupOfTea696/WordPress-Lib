<?php

namespace CupOfTea\WordPress\Theme;

abstract class Theme extends Service
{
    abstract public function registerServices();
    
    protected function register($alias, $service)
    {
        $alias = 'theme.' . $alias;
        
        $this->app->singleton($service);
        $this->app->alias($service, $alias);
        
        $concrete = $this->app->make($service);
        
        if (method_exists($concrete, 'boot')) {
            $this->app->call([$concrete, 'boot']);
        }
    }
}