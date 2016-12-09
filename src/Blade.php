<?php

namespace CupOfTea\WordPress;

use BadMethodCallException;
use Illuminate\Support\Str;

class Blade extends Service
{
    protected $factory;
    
    protected $blade;
    
    protected $files;
    
    protected $cachePath;
    
    private $stack = [];
    
    private $typedStacks = [];
    
    private $bladeStripsParentheses;
    
    protected $directives = [
        'wpposts',
        'endwpposts',
        'wpquery',
        'endwpquery',
        'wploop',
        'wpempty',
        'endwploop',
        'acf',
        'ifacf',
        'endifacf',
        'acfrow',
        'endacfrow',
        'acfloop',
        'acfempty',
        'endacfloop',
        'acflayout',
        'elseacflayout',
        'endacflayout',
    ];
    
    public function boot()
    {
        $this->cachePath = config('view.compiled');
        
        $this->factory = app('view');
        $this->blade = app('view')->getEngineResolver()->resolve('blade')->getCompiler();
        $this->files = app('files');
        
        $this->factory->addExtension('php', 'blade');
        
        $this->bladeDirectives();
        $this->checkBladeStripsParentheses();
        
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
    
    public function compileView($path, $data = [])
    {
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
    
    protected function checkBladeStripsParentheses()
    {
        $this->blade->directive('__blade_wp_test_strips_parentheses', function ($expression) {
            return $expression;
        });
        
        $this->bladeStripsParentheses = $this->blade->compileString('@__blade_wp_test_strips_parentheses()') !== '()';
    }
    
    protected function openStack($type, $params = [])
    {
        $prev = last($this->stack);
        
        $item = [
            'type' => $type,
            'id' => $prev['id'] + 1,
        ];
        
        $item = array_merge($params, $item);
        
        $this->stack[] = &$item;
        $this->typedStacks[$type][] = &$item;
        
        return $item['id'];
    }
    
    protected function closeStack($type)
    {
        $current = $this->last($this->stack);
        
        if ($current['type'] != $type) {
            throw new BadMethodCallException('Invalid end directive. Trying to close ' . $type . ' but was expecting ' . $current['type'] . '.');
        }
        
        array_pop($this->stack);
        array_pop($this->typedStacks[$type]);
        
        return $current;
    }
    
    public function compileWpposts()
    {
        $this->openStack('wpposts');
        
        return '<?php if (have_posts()): ?>';
    }
    
    public function compileEndwpposts()
    {
        $this->closeStack('wpposts');
        
        return '<?php endif; ?>';
    }
    
    public function compileWpquery($expression)
    {
        $expression = $this->normalizeExpression($expression);
        $id = $this->openStack('wpquery');
        
        return "<?php \$query = \$__blade_wp_query_{$id} = new WP_Query({$expression}); if (\$__blade_wp_query_{$id}->have_posts()): ?>";
    }
    
    public function compileEndwpquery()
    {
        $this->closeStack('wpquery');
        
        $query = '';
        
        foreach (array_reverse($this->stack) as $item) {
            if ($item['type'] == 'wpquery') {
                $query = " \$query = \$__blade_wp_query_{$item['id']};";
                
                break;
            }
        }
        
        return "<?php endif;{$query} ?>";
    }
    
    public function compileWploop()
    {
        if ($parent = $this->lastOfType('wpposts') ?: $parent = $this->lastOfType('wpquery')) {
            $related = [];
            
            if (! empty($this->typedStacks['wploop'])) {
                $related = array_where($this->typedStacks['wploop'], function ($item) use ($parent) {
                    if (empty($item['rel'])) {
                        return false;
                    }
                    
                    return $item['rel'] == $parent['id'];
                });
            }
            
            if (count($related) == 0) {
                $id = $this->openStack('wploop', ['rel' => $parent['id'], 'open' => true]);
                
                if ($parent['type'] == 'wpposts') {
                    return "<?php while (have_posts()): the_post(); \$__blade_wp_post_{$id} = \$post = get_post(); ?>";
                }
                
                if ($parent['type'] == 'wpquery') {
                    return "<?php while (\$__blade_wp_query_{$parent['id']}->have_posts()): \$__blade_wp_query_{$parent['id']}->the_post(); \$__blade_wp_post_{$id} = \$post = \$__blade_wp_query_{$parent['id']}->get_post(); ?>";
                }
            }
        }
        
        $id = $this->openStack('wploop', ['open' => true]);
        
        return "<?php if (have_posts()): while (have_posts()): the_post(); \$__blade_wp_post_{$id} = \$post = get_post(); ?>";
    }
    
    public function compileWpempty()
    {
        $current = $this->last($this->stack);
        
        if ($current['type'] == 'wploop') {
            $current['open'] = false;
            $parent = ! empty($current['rel']) ? $this->get($current['rel']) : false;
            
            if ($parent && $parent['type'] == 'wpquery') {
                $post = '';
                
                foreach (array_reverse($this->stack) as $item) {
                    if ($item == $current) {
                        continue;
                    }
                    
                    if ($item['type'] == 'wploop') {
                        $post = " \$post = \$__blade_wp_post_{$item['id']};";
                        
                        break;
                    }
                }
                
                return "<?php endwhile; wp_reset_postdata();{$post} else: ?>";
            }
            
            return '<?php endwhile; else: ?>';
        }
        
        return '<?php else: ?>';
    }
    
    public function compileEndwploop()
    {
        $current = $this->last($this->stack);
        $parent = ! empty($current['rel']) ? $this->get($current['rel']) : false;
        $endif = $parent ? '' : ' endif;';
        
        $this->closeStack('wploop');
        
        if ($current['open']) {
            if ($parent && $parent['type'] == 'wpquery') {
                $post = '';
                
                foreach (array_reverse($this->stack) as $item) {
                    if ($item['type'] == 'wploop') {
                        $post = " \$post = \$__blade_wp_post_{$item['id']};";
                        
                        break;
                    }
                }
                
                return "<?php endwhile; wp_reset_postdata();{$post}{$endif} ?>";
            }
            
            return "<?php endwhile;{$endif} ?>";
        }
        
        return "<?php{$endif} ?>";
    }
    
    public function compileAcf($expression)
    {
        $expression = $this->normalizeExpression($expression);
        
        if ($expression) {
            return "<?php echo e(app('wp')->acf({$expression})); ?>";
        }
        
        $current = $this->lastOfType('acfrow');
        
        if ($current) {
            return "<?php echo e(\$__acf_value_{$current['id']}); ?>";
        }
        
        return '';
    }
    
    public function compileIfacf($expression)
    {
        $expression = $this->normalizeExpression($expression);
        $id = $this->openStack('ifacf');
        
        return "<?php if (\$__acf_value_{$id} = get_field({$expression})): ?>";
    }
    
    public function compileEndifacf()
    {
        $this->closeStack('ifacf');
        
        return '<?php endif; ?>';
    }
    
    public function compileAcfrow($expression)
    {
        $expression = $this->normalizeExpression($expression);
        
        $this->openStack('acfrow', ['expression' => $expression]);
        
        return "<?php if (have_rows({$expression})): ?>";
    }
    
    public function compileEndacfrow()
    {
        $this->closeStack('acfrow');
        
        return '<?php endif; ?>';
    }
    
    public function compileAcfloop($expression)
    {
        $expression = $this->normalizeExpression($expression);
        
        if ($parent = $this->lastOfType('acfrow')) {
            $related = [];
            
            if (! empty($this->typedStacks['acfloop'])) {
                $related = array_where($this->typedStacks['acfloop'], function ($item) use ($parent) {
                    if (empty($item['rel'])) {
                        return false;
                    }
                    
                    return $item['rel'] == $parent['id'];
                });
            }
            
            if (count($related) == 0) {
                $id = $this->openStack('acfloop', ['rel' => $parent['id'], 'open' => true]);
                
                if (! $expression) {
                    $expression = $parent['expression'];
                }
                
                return "<?php while(have_rows({$expression})): the_row(); ?>";
            }
        }
        
        $id = $this->openStack('acfloop', ['open' => true]);
        
        return "<?php if (have_rows({$expression})): while(have_rows({$expression})): the_row(); ?>";
    }
    
    public function compileAcfempty()
    {
        $current = $this->last($this->stack);
        
        if ($current['type'] == 'acfloop') {
            $current['open'] = false;
            
            return '<?php endwhile; else: ?>';
        }
        
        return '<?php else: ?>';
    }
    
    public function compileEndacfloop()
    {
        $current = $this->last($this->stack);
        $parent = ! empty($current['rel']) ? $this->get($current['rel']) : false;
        
        $this->closeStack('acfloop');
        
        $endwhile = $current['open'] ? ' endwhile;' : '';
        $endif = $parent ? '' : ' endif;';
        
        return "<?php{$endwhile}{$endif} ?>";
    }
    
    public function compileAcflayout($expression)
    {
        $expression = $this->normalizeExpression($expression);
        $id = $this->openStack('acflayout');
        
        return "<?php if ((\$__acf_layout_{$id} = get_row_layout()) == {$expression}): ?>";
    }
    
    public function compileElseacflayout($expression)
    {
        $expression = $this->normalizeExpression($expression);
        $current = $this->lastOfType('acfrow');
        
        return "<?php elseif (\$__acf_layout_{$current['id']} == {$expression}): ?>";
    }
    
    public function compileEndacflayout($expression)
    {
        $expression = $this->normalizeExpression($expression);
        $this->closeStack('acflayout');
        
        return '<?php endif; ?>';
    }
    
    private function get($id)
    {
        foreach ($this->stack as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
        }
        
        return false;
    }
    
    private function &last($array)
    {
        return $array[count($array) - 1];
    }
    
    private function lastOfType($type)
    {
        if (empty($this->typedStacks[$type])) {
            return false;
        }
        
        return $this->last($this->typedStacks[$type]);
    }
    
    private function normalizeExpression($expression)
    {
        if (! $this->bladeStripsParentheses) {
            if (Str::startsWith($expression, '(') && Str::endsWith($expression, ')')) {
                return Str::substr($expression, 1, -1);
            }
        }
        
        return $expression;
    }
}
