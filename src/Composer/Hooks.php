<?php

namespace CupOfTea\WordPress\Composer;

class Hooks
{
    protected static $io;
    
    protected static $vendorDir;
    
    public static function postPkgInstall($e)
    {
        self::setProperties($e);
        
        $pkg = $e->getOperation()->getPackage();
        
        if ($pkg->getName() === 'johnpbloch/wordpress') {
            self::rmDefaultPlugins();
            self::rmDefaultThemes();
            self::createWpConfig();
        }
    }
    
    public static function prePkgUpdate($e)
    {
        self::setProperties($e);
        
        $pkg = $e->getOperation()->getInitialPackage();
        
        if ($pkg->getName() === 'johnpbloch/wordpress') {
            self::backupPlugins();
            self::backupThemes();
            self::backupUploads();
        }
    }
    
    public static function postPkgUpdate($e)
    {
        self::setProperties($e);
        
        $pkg = $e->getOperation()->getTargetPackage();
        
        if ($pkg->getName() === 'johnpbloch/wordpress') {
            self::restorePlugins();
            self::restoreThemes();
            self::restoreUploads();
            self::createWpConfig();
        }
    }
    
    protected static function backupPlugins()
    {
        self::backup('public/wp/wp-content/plugins');
    }
    
    protected static function backupThemes()
    {
        self::backup('public/wp/wp-content/themes');
    }
    
    protected static function backupUploads()
    {
        self::backup('public/wp/wp-content/uploads');
    }
    
    protected static function restorePlugins()
    {
        self::restore('public/wp/wp-content/plugins');
    }
    
    protected static function restoreThemes()
    {
        self::restore('public/wp/wp-content/themes');
    }
    
    protected static function restoreUploads()
    {
        self::restore('public/wp/wp-content/uploads');
    }
    protected static function rmDefaultPlugins()
    {
        $dir = self::path('public/wp/wp-content/plugins');
        
        self::delTree($dir);
        mkdir($dir);
    }
    
    protected static function rmDefaultThemes()
    {
        $dir = self::path('public/wp/wp-content/themes');
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path) && preg_match('/themes\/twenty.*$/', $path)) {
                self::delTree($path);
            }
        }
    }
    
    protected static function createWpConfig()
    {
        copy(self::path('app/stubs/wp-config.php.stub'), self::path('public/wp/wp-config.php'));
    }
    
    protected static function setProperties($e)
    {
        self::$io = $e->getIO();
        self::$vendorDir = $e->getComposer()->getConfig()->get('vendor-dir');
    }
    
    protected static function path($path = '')
    {
        return dirname(self::$vendorDir) . DIRECTORY_SEPARATOR . $path;
    }
    
    protected static function delTree($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::delTree($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    protected static function backup($path)
    {
        $dir = preg_replace('/.*\/([^\/]+)/', '$1', $path);
        
        exec('mkdir ' . self::path('.tmp'));
        exec('cp -r ' . self::path($path) . ' ' . self::path('.tmp/' . $dir));
    }
    
    protected static function restore($path)
    {
        $dir = preg_replace('/.*\/([^\/]+)/', '$1', $path);
        
        exec('mv ' . self::path('.tmp/' . $dir) . '/* ' . self::path($path));
        
        if (((int) exec('find .tmp/ | wc -l')) - 1 === 0) {
            exec('rm -rf ' . self::path('.tmp/'));
        }
    }
}
