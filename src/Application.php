<?php

namespace CupOfTea\WordPress;

use Exception;
use ErrorException;
use Monolog\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use CupOfTea\Package\Package;
use Illuminate\Container\Container;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use CupOfTea\WordPress\Foundation\Bootstrap\Environment;
use CupOfTea\Package\Contracts\Package as PackageContract;
use Symfony\Component\Debug\Exception\FatalErrorException;
use CupOfTea\WordPress\Foundation\Bootstrap\BootApplication;
use CupOfTea\WordPress\Foundation\Bootstrap\RegisterFacades;
use CupOfTea\WordPress\Foundation\Bootstrap\RegisterServices;
use CupOfTea\WordPress\Foundation\Bootstrap\ReadConfiguration;
use CupOfTea\WordPress\Foundation\Bootstrap\RegisterProviders;
use CupOfTea\WordPress\Foundation\Bootstrap\RegisterThemeAutoloader;

class Application extends Container implements PackageContract
{
    use Package;
    
    /**
     * Package Vendor.
     *
     * @const string
     */
    const VENDOR = 'CupOfTea';
    
    /**
     * Package Name.
     *
     * @const string
     */
    const PACKAGE = 'WordPress-Lib';
    
    /**
     * Package Version.
     *
     * @const string
     */
    const VERSION = '1.5.4';
    
    /**
     * The base path for the WordPress installation.
     *
     * @var string
     */
    protected $basePath;
    
    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storagePath;
    
    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;
    
    /**
     * All of the registered services.
     *
     * @var array
     */
    protected $services = [];
    
    /**
     * All of the registered service providers.
     *
     * @var array
     */
    protected $serviceProviders = [];
    
    /**
     * The Application bootstrappers.
     *
     * @var array
     */
    public $bootstrappers = [
        RegisterThemeAutoloader::class,
        ReadConfiguration::class,
        RegisterProviders::class,
        RegisterServices::class,
        RegisterFacades::class,
        BootApplication::class,
    ];
    
    /**
     * Create a new WordPress Application instance.
     *
     * @param  string|null  $basePath
     * @param  \Composer\Autoload\ClassLoader|null  $composer
     * @return void
     */
    public function __construct($basePath = null, $composer = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }
        
        if ($composer) {
            $this->instance('composer', $composer);
        }
        
        $this->bootstrapContainer();
        $this->registerErrorHandling();
    }
    
    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }
        
        $this->make('blade')->boot();
        
        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });
        
        $this->booted = true;
    }
    
    /**
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer()
    {
        static::setInstance($this);
        
        $this->instance('app', $this);
        
        $this->bootstrapEnvironment();
        
        $this->bindPathsInContainer();
        $this->registerContainerAliases();
        $this->registerCoreServices();
        $this->registerLogBindings();
        $this->registerTheme();
    }
    
    protected function bootstrapEnvironment()
    {
        $this->bootstrapWith([Environment::class]);
    }
    
    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers)
    {
        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
    }
    
    /**
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $aliases = [
            'app' => ['Illuminate\Container\Container', 'Illuminate\Contracts\Container\Container'],
            'config' => ['Illuminate\Config\Repository', 'Illuminate\Contracts\Config\Repository'],
            'log' => 'Psr\Log\LoggerInterface',
        ];
        
        foreach ($aliases as $key => $aliases) {
            foreach ((array) $aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
    
    protected function registerCoreServices()
    {
        $this->registerServices([
            'blade' => 'CupOfTea\WordPress\View\Blade',
            'wp' => [
                '_self' => 'CupOfTea\WordPress\WordPress',
                'page' => 'CupOfTea\WordPress\WordPress\Page',
                'post' => 'CupOfTea\WordPress\WordPress\Post',
                'theme' => 'CupOfTea\WordPress\WordPress\Theme',
                'archive' => 'CupOfTea\WordPress\WordPress\Archive',
            ],
        ]);
        
        $providers = [
            'Illuminate\View\ViewServiceProvider',
            'Illuminate\Events\EventServiceProvider',
            'Illuminate\Filesystem\FilesystemServiceProvider',
        ];
        
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }
    
    protected function registerServices($services)
    {
        foreach ($services as $alias => $concrete) {
            if (is_array($concrete)) {
                $self = Arr::pull($concrete, '_self');
                $subservices = array_combine(
                    array_map(function ($key) use ($alias) {
                        return $alias . '.' . $key;
                    }, array_keys($concrete)),
                    $concrete
                );
                $concrete = $self;
                
                $this->registerServices($subservices);
            }
            
            $this->registerService($concrete, $alias);
        }
    }
    
    /**
     * Get or check the current application environment.
     *
     * @param  mixed
     * @return string
     */
    public function environment()
    {
        $env = env('APP_ENV', 'production');
        
        if (func_num_args() > 0) {
            $patterns = is_array(func_get_arg(0)) ? func_get_arg(0) : func_get_args();
            
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $env)) {
                    return true;
                }
            }
            
            return false;
        }
        
        return $env;
    }
    
    /**
     * Set the error handling for the application.
     *
     * @return void
     */
    protected function registerErrorHandling()
    {
        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new ErrorException($message, 0, $level, $file, $line);
            }
        });
        
        set_exception_handler(function ($e) {
            $this->handleUncaughtException($e);
        });
        
        register_shutdown_function(function () {
            if (! is_null($error = error_get_last()) && $this->isFatalError($error['type'])) {
                $this->handleUncaughtException(new FatalErrorException(
                    $error['message'], $error['type'], 0, $error['file'], $error['line']
                ));
            }
        });
    }
    
    /**
     * Determine if the error type is fatal.
     *
     * @param  int  $type
     * @return bool
     */
    protected function isFatalError($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }
    
    /**
     * Handle an uncaught exception instance.
     *
     * @param  Exception  $e
     * @return void
     */
    protected function handleUncaughtException($e)
    {
        $handler = $this->make('Illuminate\Contracts\Debug\ExceptionHandler');
        
        $handler->report($e);
        
        $handler->render($e);
    }
    
    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerLogBindings()
    {
        $this->singleton('Psr\Log\LoggerInterface', function () {
            return new Logger('wordpress', [$this->getMonologHandler()]);
        });
    }
    
    /**
     * Get the Monolog handler for the application.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected function getMonologHandler()
    {
        return (new RotatingFileHandler(storage_path('logs/wordpress.log'), 5, Logger::DEBUG))
                            ->setFormatter(new LineFormatter(null, null, true, true));
    }
    
    protected function registerTheme()
    {
        $this->singleton('theme', function ($app) {
            $app->bootstrapWith($app->bootstrappers);
            
            $theme = $app->make(Str::studly(wp_get_theme()->Name) . '\\Theme');
            
            $theme->registerServices();
            
            if (method_exists($theme, 'boot')) {
                $this->call([$theme, 'boot']);
            }
            
            return $theme;
        });
    }
    
    protected function registerService($abstract, $alias = null)
    {
        $this->singleton($abstract);
        
        if ($alias) {
            $this->alias($abstract, $alias);
        }
        
        $this->services[] = $abstract;
    }
    
    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  array  $options
     * @param  bool   $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $options = [], $force = false)
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }
        
        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProviderClass($provider);
        }
        
        $provider->register();
        
        // Once we have registered the service we will iterate through the options
        // and set each of them on the application so they will be available on
        // the actual loading of the service objects and for developer usage.
        foreach ($options as $key => $value) {
            $this[$key] = $value;
        }
        
        $this->serviceProviders[] = $provider;
        
        if ($this->booted) {
            $this->bootProvider($provider);
        }
        
        return $provider;
    }
    
    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @return \Illuminate\Support\ServiceProvider|null
     */
    public function getProvider($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);
        
        return Arr::first($this->serviceProviders, function ($key, $value) use ($name) {
            return $value instanceof $name;
        });
    }
    
    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return \Illuminate\Support\ServiceProvider
     */
    public function resolveProviderClass($provider)
    {
        return new $provider($this);
    }
    
    /**
     * Boot the given service provider.
     *
     * @param  \CupOfTea\WordPress\Service | \Illuminate\Support\ServiceProvider  $provider
     * @return mixed
     */
    protected function bootProvider($boot)
    {
        if (method_exists($boot, 'boot')) {
            return $this->call([$boot, 'boot']);
        }
    }
    
    /**
     * Resolve the given type from the container.
     *
     * (Overriding Container::make)
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);
        
        return parent::make($abstract, $parameters);
    }
    
    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');
        
        return $this;
    }
    
    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path', $this->path());
        
        foreach (['base', 'config', 'public', 'storage', 'wp'] as $name) {
            $path = $this->{$name . 'Path'}();
            
            $this->instance('path.' . $name, $path);
            define(strtoupper($name) . '_PATH', $path);
        }
    }
    
    /**
     * Get the path to the application "app" directory.
     *
     * @return string
     */
    public function path()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app';
    }
    
    /**
     * Get the base path of the Laravel installation.
     *
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }
    
    /**
     * Get the path to the application configuration files.
     *
     * @return string
     */
    public function configPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config';
    }
    
    /**
     * Get the path to the application public files.
     *
     * @return string
     */
    public function publicPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . env('APP_PUBLIC');
    }
    
    /**
     * Get the path to the storage directory.
     *
     * @return string
     */
    public function storagePath()
    {
        return $this->storagePath ?: $this->basePath . DIRECTORY_SEPARATOR . 'storage';
    }
    
    /**
     * Get the path to the application public files.
     *
     * @return string
     */
    public function wpPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . env('APP_PUBLIC') . DIRECTORY_SEPARATOR . 'wp';
    }
}
