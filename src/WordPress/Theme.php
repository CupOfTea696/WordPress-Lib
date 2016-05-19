<?php

namespace CupOfTea\WordPress\WordPress;

use CupOfTea\WordPress\Service;

class Theme extends Service
{
    public function getRoot()
    {
        $template = str_replace('%2F', '/', rawurlencode(get_template()));
        $themeRoots = get_theme_roots();
        
        $root = $themeRoots[$template] . '/' . $template;
        
        if (preg_match('/^\/themes/', $root)) {
            $root = $this->app->wpPath() . '/wp-content' . $root;
        }
        
        return $root;
    }
    
    public function getUri()
    {
        return str_replace($this->app->publicPath(), '', $this->getRoot());
    }
}

