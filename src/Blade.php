<?php

namespace CupOfTea\WordPress;

use Illuminate\Support\Str;

use InvalidArgumentException;

class Blade extends Service
{
    protected $factory;
    
    protected $blade;
    
    protected $files;
    
    protected $cachePath;
    
    protected $loop = -1;
    
    protected $loopStack = [];
    
    protected $acfIfCounter = -1;
    
    protected $directives = [
        'wploop',
        'wpempty',
        'endwploop',
        'wpquery',
        'endwpquery',
        'acf',
        'ifacf',
        'endifacf',
        'acfrepeater',
        'acfempty',
        'endacfrepeater',
    ];
    
    public function boot()
    {
        $this->cachePath = config('view.compiled');
        
        $this->factory = app('view');
        $this->blade = app('view')->getEngineResolver()->resolve('blade')->getCompiler();
        $this->files = app('files');
        
        $this->factory->addExtension('php', 'blade');
        
        $this->bladeDirectives();
        
        $filters = [
            'template_include',
            'index_template',
            'page_template',
            'bp_template_include',
        ];
        
        foreach ($filters as $filter) {
            add_filter($filter, [$this, 'renderView']);
        }
    }
    
    public function compileView($path, $data = []) {
        return $this->factory->file($path, $data)->render();
    }
    
    public function renderView($path)
    {
        if (! $path || Str::startsWith($path, $this->cachePath)) {
            return $path;
        }
        
        global $__view;
        
        $__view = $this->factory->file($path);
        $compiled = $this->blade->getCompiledPath(__FILE__);
        
        if ($this->blade->isExpired(__FILE__)) {
            $this->files->put($compiled, '<?php echo $__view->render(); ?>');
        }
        
        return $compiled;
    }
    
    protected function bladeDirectives()
    {
        foreach ($this->directives as $directive) {
            if (method_exists($this, $method = 'compile' . ucfirst($directive))) {
                $this->blade->directive($directive, [$this, $method]);
            }
        }
    }
    
    protected function startLoop()
    {
        $this->loopStack[] = ['open' => true];
        $this->loop++;
    }
    
    protected function closeLoop()
    {
        $this->loopStack[$this->loop]['open'] = false;
    }
    
    protected function endLoop()
    {
        $open = $this->loopStack[$this->loop]['open'];
        
        array_pop($this->loopStack);
        $this->loop--;
        
        return $open;
    }
    
    public function compileWploop()
    {
        $this->startLoop();
        
        return "<?php if (have_posts()): while (have_posts()): the_post(); \$post = get_post(); ?>";
    }
    
    public function compileWpempty()
    {
        $this->closeLoop();
        
        return "<?php endwhile; else: ?>";
    }
    
    public function compileEndwploop()
    {
        $open = $this->endLoop();
        $endwhile = $open ? 'endwhile; ' : '';
        
        return "<?php {$endwhile}endif; ?>";
    }
    
    public function compileWpquery($expression)
    {
        $this->startLoop();
        
        return "<?php \$__blade_wp_query = new WP_Query{$expression}; if (\$__blade_wp_query->have_posts()): while (\$__blade_wp_query->have_posts()): \$__blade_wp_query->the_post(); \$post = \$__blade_wp_query->get_post(); ?>";
    }
    
    public function compileEndwpquery()
    {
        $open = $this->endLoop();
        $endwhile = $open ? 'endwhile; ' : '';
        
        return "<?php {$endwhile}endif; wp_reset_postdata(); ?>";
    }
    
    public function compileAcf($expression)
    {
        if ($expression && $expression != '()') {
            return "<?php echo e(app('wp')->acf{$expression}); ?>";
        }
        
        return $this->compileAcfifvalue();
    }
    
    public function compileIfacf($expression)
    {
        $this->acfIfCounter++;
        
        return "<?php if (\$__acf_value_{$this->acfIfCounter} = get_field{$expression}): ?>";
    }
    
    public function compileAcfifvalue()
    {
        return "<?php echo e(\$__acf_value_{$this->acfIfCounter}); ?>";
    }
    
    public function compileEndifacf()
    {
        $this->acfIfCounter--;
        
        return "<?php endif; ?>";
    }
    
    public function compileAcfrepeater($expression)
    {
        $this->startLoop();
        
        return "<?php if (have_rows{$expression}): while(have_rows{$expression}): the_row(); ?>";
    }
    
    public function compileAcfempty()
    {
        return $this->compileWpempty();
    }
    
    public function compileEndacfrepeater()
    {
        return $this->compileEndwploop();
    }
}
