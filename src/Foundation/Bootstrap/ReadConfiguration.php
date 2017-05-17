<?php

namespace CupOfTea\WordPress\Foundation\Bootstrap;

use Illuminate\Config\Repository;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Config\Repository as RepositoryContract;

class ReadConfiguration
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Container $app)
    {
        $items = [];
        
        $app->instance('config', $config = new Repository($items));
        
        // Next we will spin through all of the configuration files in the configuration
        // directory and load each one into the repository. This will make all of the
        // options available to the developer for use in various parts of this app.
        $this->loadConfigurationFiles($app, $config);
        
        mb_internal_encoding('UTF-8');
    }
    
    /**
     * Load the configuration items from all of the files.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    protected function loadConfigurationFiles(Container $app, RepositoryContract $config)
    {
        foreach ($this->getConfigurationFiles($app) as $key => $path) {
            $config->set($key, require $path);
        }
        
        foreach ($this->getThemeConfigurationFiles($app) as $key => $path) {
            if ($config->has($key)) {
                $values = require $path;
                
                $config->set($key, array_merge($config->get($key), $values));
            } else {
                $config->set($key, require $path);
            }
        }
    }
    
    /**
     * Get all of the configuration files for the application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array
     */
    protected function getConfigurationFiles(Container $app)
    {
        $files = [];
        
        foreach (Finder::create()->files()->name('*.php')->in($app->configPath()) as $file) {
            $nesting = $this->getConfigurationNesting($file);
            
            $files[$nesting . basename($file->getRealPath(), '.php')] = $file->getRealPath();
        }
        
        return $files;
    }
    
    /**
     * Get all of the configuration files for the theme.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array
     */
    protected function getThemeConfigurationFiles(Container $app)
    {
        $files = [];
        $config_path = $app->make('config')->get('theme.config_path', realpath($app->make('wp.theme')->getRoot()) . '/config');
        
        if (file_exists($config_path) && is_dir($config_path)) {
            foreach (Finder::create()->files()->name('*.php')->in($config_path) as $file) {
                $nesting = $this->getThemeConfigurationNesting($file, $config_path);
                
                $files[$nesting . basename($file->getRealPath(), '.php')] = $file->getRealPath();
            }
        }
        
        return $files;
    }
    
    /**
     * Get the configuration file nesting path.
     *
     * @param  \Symfony\Component\Finder\SplFileInfo  $file
     * @return string
     */
    private function getConfigurationNesting(SplFileInfo $file)
    {
        $directory = dirname($file->getRealPath());
        
        if ($tree = trim(str_replace(config_path(), '', $directory), DIRECTORY_SEPARATOR)) {
            $tree = str_replace(DIRECTORY_SEPARATOR, '.', $tree) . '.';
        }
        
        return $tree;
    }
    
    /**
     * Get the theme configuration file nesting path.
     *
     * @param  \Symfony\Component\Finder\SplFileInfo  $file
     * @return string
     */
    private function getThemeConfigurationNesting(SplFileInfo $file, $config_path)
    {
        $directory = dirname($file->getRealPath());
        
        if ($tree = trim(str_replace($config_path, '', $directory), DIRECTORY_SEPARATOR)) {
            $tree = str_replace(DIRECTORY_SEPARATOR, '.', $tree) . '.';
        }
        
        return $tree;
    }
}
