<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;

class RegisterThemeAutoloader
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        if (! $app->bound('composer')) {
            $composer = new \Composer\Autoload\ClassLoader();
            $composer->register(true);
            
            $app->instance('composer', $composer);
        }
        
        $composer = $app->make('composer');
        $themeDir = get_template_directory();
        
        $namespace = Str::studly(wp_get_theme()->Name) . '\\';
        $path = [];
        
        foreach (['lib', 'library'] as $directory) {
            $fullPath = $themeDir . '/' . $directory;
            
            if (file_exists($fullPath) && is_dir($fullPath)) {
                $path[] = $fullPath;
            }
        }
        
        $libcfg = $themeDir . '/.libcfg';
        
        if (file_exists($libcfg) && is_file($libcfg)) {
            $libcfg = array_filter(array_map('trim', explode(PHP_EOL, $cfg)));
            
            $path = array_merge($path, $libcfg);
        }
        
        $composer->addPsr4($namespace, $path);
    }
}
