<?php

namespace CupOfTea\WordPress\Theme;

abstract class Theme extends Service
{
    private $services = [];
    
    abstract public function registerServices();
    
    protected function register($alias, $service)
    {
        $alias = 'theme.' . $alias;
        
        $this->app->singleton($service);
        $this->app->alias($service, $alias);
        
        $this->services[$service] = $concrete = $this->app->make($service);
        
        if (method_exists($concrete, 'boot')) {
            $this->app->call([$concrete, 'boot']);
        }
    }
    
    public function invoke($service)
    {
        return $this->services[$service];
    }
}
