<?php

namespace CupOfTea\WordPress\Theme;

abstract class Theme extends Service
{
    private $services = [];
    
    abstract public function registerServices();
    
    protected function register($alias, $service)
    {
        $this->app->singleton($service);
        $this->app->alias($service, 'theme.' . $alias);
        
        $this->services[$alias] = $this->services[$service] = $concrete = $this->app->make($service);
        
        if (method_exists($concrete, 'boot')) {
            $this->app->call([$concrete, 'boot']);
        }
    }
    
    public function __invoke($service)
    {
        return $this->services[$service];
    }
}
